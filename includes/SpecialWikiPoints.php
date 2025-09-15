<?php
namespace MediaWiki\Extension\WikiPoints;
use MediaWiki\SpecialPage\SpecialPage;
use HTMLForm;
use MediaWiki\Title\Title;
use MediaWiki\MediaWikiServices;


class SpecialWikiPoints extends SpecialPage {
    public function __construct() {
        parent::__construct( 'WikiPoints' );
    }

    public function execute( $subPage ) {
		$out = $this->getOutput();
        $out->setPageTitle( 'Get WikiPoints for a user' );
		$formDescriptor = [
				'username' => [
					'section' => 'wikipoints-special-form-name',
					'label-message' => 'wikipoints-special-form-name-label',
					'type' => 'user',
				],
			];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm
		->setSubmitText( 'Submit' )
		->setSubmitCallback( [$this, 'trySubmit'] )
		->show();
		if ( $subPage ) {
			$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
			$dbr = $dbProvider->getReplicaDatabase();
			$username = str_replace( '_', ' ', $subPage );
			$userID = $this->getUserID( $username );
			if ( $userID == 0 ) {
				$out->addWikiTextAsContent( "$username does not exist." );
			} else {
				$lang = $this->getLanguage();
				$wikiPoints = $lang->formatNum( $this->calculateWikiPoints( $userID ) );
				$out->addWikiTextAsContent( "$username has '''$wikiPoints''' WikiPoints." );
			}
		}
	}

	public function trySubmit( $formData ) {
        $out = $this->getOutput();
		$out->redirect( Title::makeTitleSafe( NS_SPECIAL, 'WikiPoints/' .$formData['username'] )->getLocalURL() );
		return true;
	}
	private function getUserID( $user ) {
        $userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$userID = $userFactory->newFromName( $user )->getActorId();
		return $userID;
	}
	
	private function calculateWikiPoints( $userID ) {
		$dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
		$dbr = $dbProvider->getReplicaDatabase();
        $wikiPoints = $dbr->newSelectQueryBuilder()
        ->select( [ 'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )' ] )
        ->from( 'revision', 'r' )
        ->leftJoin( 'revision', 'p', 'r.rev_parent_id = p.rev_id' )
        ->where( [ 'r.rev_actor' => $userID ] )
        ->caller( __METHOD__ )
        ->fetchRow()->wiki_points;
		return $wikiPoints;
	}
}
