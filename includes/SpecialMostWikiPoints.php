<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialMostWikiPoints extends SpecialPage {

	public function __construct() {
			parent::__construct( 'MostWikiPoints' );
			$this->MWServices = MediaWikiServices::getInstance();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();
		$this->setHeaders();
		$dbProvider = $this->MWServices->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase();
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
		$linkRenderer = $this->MWServices->getLinkRenderer();
		foreach ( $res as $row ) {
			$title = Title::newFromText( "Special:Contributions/{$row->actor_name}" );
			$out->addHTML( Html::openElement( 'tr' ) );
			$out->addHTML( Html::element( 'td', [], $lang->formatNum( $i ) ) );
			$out->addHTML( Html::rawElement( 'td', [], $linkRenderer->makeLink( $title, $row->actor_name, [] ) ) );
			$out->addHTML( Html::element( 'td', [], $lang->formatNum( $row->wiki_points ) ) );
			$out->addHTML( Html::closeElement( 'tr' ) );
			$i++;
		}
		$out->addHTML( Html::closeElement( 'table' ) );
	}
}
