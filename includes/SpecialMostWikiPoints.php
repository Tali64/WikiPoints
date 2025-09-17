<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
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
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ): void {
		$out = $this->getOutput();
		$this->setHeaders();

		$dbr = $this->connectionProvider->getReplicaDatabase();
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
		$res = $qb->fetchResultSet();

		$out->addHTML( Html::openElement( 'table', [ 'class' => 'wikitable' ] ) );
		$out->addHTML( Html::openElement( 'tr' ) );
		$out->addHTML( Html::element( 'th', [], 'Rank' ) );
		$out->addHTML( Html::element( 'th', [], 'Username' ) );
		$out->addHTML( Html::element( 'th', [], 'WikiPoints' ) );
		$out->addHTML( Html::closeElement( 'tr' ) );

		$i = 1;
		$lang = $this->getLanguage();
		foreach ( $res as $row ) {
			$title = $this->specialPageFactory->getPage( 'Contributions' )->getPageTitle( $row->actor_name );
			$out->addHTML( Html::openElement( 'tr' ) );
			$out->addHTML( Html::element( 'td', [], $lang->formatNum( $i ) ) );
			$out->addHTML( Html::rawElement( 'td', [], $this->linkRenderer->makeLink( $title, $row->actor_name ) ) );
			$out->addHTML( Html::element( 'td', [], $lang->formatNum( $row->wiki_points ) ) );
			$out->addHTML( Html::closeElement( 'tr' ) );
			$i++;
		}
		$out->addHTML( Html::closeElement( 'table' ) );
	}
}
