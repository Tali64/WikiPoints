<?php
namespace MediaWiki\Extension\WikiPoints;
use SpecialPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Html\Html;

class SpecialMostWikiPoints extends SpecialPage {
    public function __construct() {
        parent::__construct( 'MostWikiPoints' );
    }

    public function execute( $subPage ) {
		$out = $this->getOutput();
        $out->setPageTitle('Users with the most WikiPoints');
	    $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase(); /** For MW < 1.40, use older method to get db connection **/
		$res = $dbr->newSelectQueryBuilder()
		->select( [ 'actor_name' ] )
		->from( 'actor' )
		->caller( __METHOD__ )->fetchResultSet();
        $wikiPointScores = [];
		foreach ( $res as $row ) {
            $userID = $this->getUserID($row->actor_name);
            $wikiPointScores[] = [
                "username" => $row->actor_name,
                "score" => $this->calculateWikiPoints($userID),
            ];
		}
        usort($wikiPointScores, function ($a, $b) {
            return $b["score"] <=> $a["score"];
        });
        $out->addHTML(Html::openElement('table', ['class' => 'wikitable']));
        $out->addHTML(Html::openElement('tr'));
        $out->addHTML(Html::element('th', [], 'Rank'));
        $out->addHTML(Html::element('th', [], 'Username'));
        $out->addHTML(Html::element('th', [], 'WikiPoints'));
        $out->addHTML(Html::closeElement('tr'));
        for ($i = 0; $i < min(count($wikiPointScores), 20); $i++) {
            $out->addHTML(Html::openElement('tr'));
            $out->addHTML(Html::element('td', [], $i + 1));
            $out->addHTML(Html::element('td', [], $wikiPointScores[$i]['username']));
            $out->addHTML(Html::element('td', [], number_format($wikiPointScores[$i]['score'])));
            $out->addHTML(Html::closeElement('tr'));
        }
        $out->addHTML(Html::closeElement('table'));
	}

	private function getUserID($user) {
		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase(); /** For MW < 1.40, use older method to get db connection **/
		$userID = $dbr->newSelectQueryBuilder()
		->select([ 'actor_id', ])
		->from('actor')
		->where([ 'actor_name' => $user ])
		->caller(__METHOD__)
		->fetchRow();
		if (!$userID) {
			return 0;
		}
		$userID = $userID->actor_id;
		return $userID;
	}
	
	private function calculateWikiPoints($userID) {
		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase(); /** For MW < 1.40, use older method to get db connection **/
		$res = $dbr->newSelectQueryBuilder()
		->select( [ 'rev_len', 'rev_parent_id' ] )
		->from( 'revision' )
		->where( [ 'rev_actor' => $userID ] )
		->caller( __METHOD__ )->fetchResultSet();
		$wikiPoints = 0;
		foreach ( $res as $row ) {
			$wikiPoints += $row->rev_len;
			if ($row->rev_parent_id > 0) {
				$parentLength = $dbr->newSelectQueryBuilder()
				->select( [ 'rev_len', ] )
				->from( 'revision' )
				->where( [ 'rev_id' => $row->rev_parent_id ] )
				->caller( __METHOD__ )
				->fetchRow();
				$wikiPoints -= $parentLength->rev_len;
			}
		}
		return $wikiPoints;
	}
}
