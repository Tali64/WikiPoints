<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialMostWikiPoints extends SpecialPage {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly LinkRenderer $linkRenderer,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserFactory $userFactory,
		private readonly WANObjectCache $cache,
	) {
		parent::__construct( 'MostWikiPoints' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ): void {
		$out = $this->getOutput();
		$this->setHeaders();

		$dbr = $this->connectionProvider->getReplicaDatabase();
		$qb = $dbr->newSelectQueryBuilder()
			->select( 'actor_id' )
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
			$user = $this->userFactory->newFromActorId( $row->actor_id );
			if ( $user->getBlock() ) {
				continue;
			}
			$points = $this->getWikiPoints( $row->actor_id );
			$rankings[] = [
				'points' => $points,
				'user' => $user->getName(),
			];
		}
		uasort( $rankings, static function ( $a, $b ) {
			return $b['points'] <=> $a['points'];
		} );
		$rankings = array_slice( $rankings, 0, 20 );
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

	private function getWikiPoints( int $userID ): int {
		$wikiPoints = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'wikipoints', 'user-points', $userID ),
			// 10 minutes
			600,
			fn() => $this->fetchWikiPointsFromDB( $userID )
		);
		return $wikiPoints;
	}

	private function fetchWikiPointsFromDB( int $userID ): int {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$totalWikiPoints = $dbr->newSelectQueryBuilder()
			->select( [ 'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )' ] )
			->from( 'revision', 'r' )
			->leftJoin( 'revision', 'p', 'r.rev_parent_id = p.rev_id' )
			->where( [ 'r.rev_actor' => $userID ] )
			->caller( __METHOD__ )
			->fetchRow()
			->wiki_points ?? 0;
		$revertedWikiPoints = $dbr->newSelectQueryBuilder()
			->select( [ 'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )' ] )
			->from( 'revision', 'r' )
			->leftJoin( 'revision', 'p', 'r.rev_parent_id = p.rev_id' )
			->leftJoin( 'change_tag', 't', 't.ct_rev_id = r.rev_id' )
			->leftJoin( 'change_tag_def', 'd', 'd.ctd_id = t.ct_tag_id' )
			->where( [ 'r.rev_actor' => $userID ] )
			->andWhere( [ 'd.ctd_name' => [ "mw-reverted", "mw-undo" ] ] )
			->caller( __METHOD__ )
			->fetchRow()
			->wiki_points ?? 0;
		return $totalWikiPoints - $revertedWikiPoints;
	}
}
