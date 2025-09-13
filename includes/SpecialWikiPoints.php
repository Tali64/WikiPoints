<?php
namespace MediaWiki\Extension\WikiPoints;
use SpecialPage;
use HTMLForm;
use MediaWiki\Title\Title;
use MediaWiki\MediaWikiServices;


class SpecialWikiPoints extends SpecialPage {
    public function __construct() {
        parent::__construct( 'WikiPoints' );
    }

    public function execute( $subPage ) {
		$out = $this->getOutput();
        $out->setPageTitle('Get WikiPoints for a user');
		$formDescriptor = [
				'username' => [
					'section' => 'wikipoints-special-form-name',
					'label-message' => 'wikipoints-special-form-name-label',
					'type' => 'text',
				],
			];

		$htmlForm = HTMLForm::factory('ooui', $formDescriptor, $this->getContext());
		$htmlForm
		->setSubmitText('Submit')
		->setSubmitCallback([$this, 'trySubmit'])
		->show();
		if ($subPage) {
			$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
			$dbr = $dbProvider->getReplicaDatabase();
			$username = str_replace('_', ' ', $subPage);
			$userID = $this->getUserID($username);
			if ($userID == 0) {
				$out->addWikiTextAsContent("$username does not exist.");
			} else {
				$wikiPoints = number_format($this->calculateWikiPoints($userID));
				$out->addWikiTextAsContent("$username has '''$wikiPoints''' WikiPoints.");
			}
		}
	}

	public function trySubmit( $formData ) {
		$request = $this->getRequest();
        $out = $this->getOutput();
		$out->redirect(Title::makeTitle(NS_SPECIAL, 'WikiPoints/' .$formData['username'])->getLocalURL());
		return true;
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
