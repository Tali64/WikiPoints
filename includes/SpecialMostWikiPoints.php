<?php
namespace MediaWiki\Extension\WikiPoints;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Html\Html;

class SpecialMostWikiPoints extends SpecialPage {
    public function __construct() {
        parent::__construct( 'MostWikiPoints' );
    }

    public function execute( $subPage ) {
		$out = $this->getOutput();
		$this->setHeaders();
        // $out->setPageTitle( $this->msg( 'wikipoints-mostpoints-title' ) );
	    $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase(); /** For MW < 1.40, use older method to get db connection **/
		$qb = $dbr->newSelectQueryBuilder()
		->select( [
			'a.actor_name',
			'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )'
		] )
		->from( 'revision', 'r' )
		->leftJoin( 'revision', 'p', [ 'r.rev_parent_id = p.rev_id' ] ) // table, alias, conds
		->join( 'actor', 'a', [ 'r.rev_actor = a.actor_id' ] )         // table, alias, conds
		->groupBy( [ 'r.rev_actor', 'a.actor_name' ] )
		->orderBy( 'wiki_points', 'DESC' )
		->limit( 20 )
		->caller( __METHOD__ );

		$res = $qb->fetchResultSet();
        $out->addHTML( Html::openElement( 'table', ['class' => 'wikitable'] ) );
        $out->addHTML( Html::openElement( 'tr' ) );
        $out->addHTML( Html::element( 'th', [], 'Rank' ) );
        $out->addHTML( Html::element( 'th', [], 'Username' ) );
        $out->addHTML( Html::element( 'th', [], 'WikiPoints' ) );
        $out->addHTML( Html::closeElement( 'tr' ) );
		$i = 1;
		$lang = $this->getLanguage();
		foreach ( $res as $row ) {
		    $out->addHTML( Html::openElement( 'tr' ) );
            $out->addHTML( Html::element( 'td', [], $lang->formatNum( $i ) ) );
            $out->addHTML( Html::rawElement( 'td', [], $out->parseInlineAsInterface( '[[Special:Contributions/' . $row->actor_name . '|' . $row->actor_name . ']]' ) ) );
            $out->addHTML( Html::element( 'td', [], $lang->formatNum( $row->wiki_points ) ) );
            $out->addHTML( Html::closeElement( 'tr' ) );
			$i++;
        }
        $out->addHTML( Html::closeElement( 'table' ) );
	}

	private function getUserID( $user ) {
        $userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$userID = $userFactory->newFromName( $user )->getActorId();
		return $userID;
	}
	
}
