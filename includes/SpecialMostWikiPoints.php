<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialMostWikiPoints extends SpecialPage {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly LinkRenderer $linkRenderer,
		private readonly SpecialPageFactory $specialPageFactory,
	) {
		parent::__construct( 'MostWikiPoints' );
		$this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ): void {
		$out = $this->getOutput();
		$this->setHeaders();

		$dbr = $this->connectionProvider->getReplicaDatabase();
		/*
		$qb = $dbr->newSelectQueryBuilder()
			->select( [
				'a.actor_name',
				'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )'
			] )
			->from( 'revision', 'r' )
			// table, alias, conds
			->leftJoin( 'revision', 'p', [ 'r.rev_parent_id = p.rev_id' ] )
			// table, alias, conds
			->join( 'actor', 'a', [ 'r.rev_actor = a.actor_id' ] )
			->groupBy( [ 'r.rev_actor', 'a.actor_name' ] )
			->orderBy( 'wiki_points', 'DESC' )
			->limit( 20 )
			->caller( __METHOD__ );
			*/
		$qb = $dbr->newSelectQueryBuilder()
			->select( [
				'actor_id',
				'actor_name'
			] )
			->from( 'actor' )
			->caller( __METHOD__ );
		$res = $qb->fetchResultSet();

		$out->addHTML( Html::openElement( 'table', [ 'class' => 'wikitable' ] ) );
		$out->addHTML( Html::openElement( 'tr' ) );
		$out->addHTML( Html::element( 'th', [], $this->msg( 'wikipoints-mostpoints-rank' )->text() ) );
		$out->addHTML( Html::element( 'th', [], $this->msg( 'wikipoints-mostpoints-username' )->text() ) );
		$out->addHTML( Html::element( 'th', [], $this->msg( 'wikipoints-mostpoints-wikipoints' )->text() ) );
		$out->addHTML( Html::closeElement( 'tr' ) );

		$rankings = [];
		foreach ( $res as $row ) {
			$points = $this->calculateWikiPoints( $row->actor_id );
			if ( $points > 0 ) {
				$rankings[] = [
					'points' => $points,
					'user' => $row->actor_name,
				];
			}
		}
		uasort( $rankings, static function ( $a, $b ) {
			return $b['points'] <=> $a['points'];
		} );
		array_slice( $rankings, 0, 20 );
		$i = 1;
		$lang = $this->getLanguage();
		foreach ( $rankings as $rank ) {
			$title = $this->specialPageFactory->getPage( 'Contributions' )->getPageTitle( $rank['user'] );
			$out->addHTML( Html::openElement( 'tr' ) );
			$out->addHTML( Html::element( 'td', [], $lang->formatNum( $i ) ) );
			$out->addHTML( Html::rawElement( 'td', [], $this->linkRenderer->makeLink( $title, $rank['user'] ) ) );
			$out->addHTML( Html::element( 'td', [], $lang->formatNum( $rank['points'] ) ) );
			$out->addHTML( Html::closeElement( 'tr' ) );
			$i++;
		}
		$out->addHTML( Html::closeElement( 'table' ) );
	}

	private function calculateWikiPoints( int $userID ): int {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$wikiPoints = $cache->getWithSetCallback(
		$cache->makeKey( 'wikipoints', 'user-points', $userID ),
		// 10 minutes
		600,
		function () use ( $userID ) {
			$dbr = $this->connectionProvider->getReplicaDatabase();
			return $this->connectionProvider->getReplicaDatabase()
				->newSelectQueryBuilder()
				->select( [ 'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )' ] )
				->from( 'revision', 'r' )
				->leftJoin( 'revision', 'p', 'r.rev_parent_id = p.rev_id' )
				->where( [ 'r.rev_actor' => $userID ] )
				->caller( __METHOD__ )
				->fetchRow()
				->wiki_points ?? 0;
		}
		);
		return $wikiPoints;
	}
}
