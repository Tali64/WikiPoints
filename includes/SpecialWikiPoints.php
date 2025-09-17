<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialWikiPoints extends SpecialPage {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserFactory $userFactory,
	) {
		parent::__construct( 'WikiPoints' );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ): void {
		$out = $this->getOutput();
		$this->setHeaders();
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
			->setSubmitCallback( [ $this, 'trySubmit' ] )
			->show();

		if ( $subPage ) {
			$username = str_replace( '_', ' ', $subPage );
			$userID = $this->userFactory->newFromName( $username )->getActorId();
			if ( $userID == 0 ) {
				$out->addWikiTextAsContent( "$username does not exist." );
			} else {
				$lang = $this->getLanguage();
				$wikiPoints = $lang->formatNum( $this->calculateWikiPoints( $userID ) );
				$out->addWikiTextAsContent(
					"[[Special:Contributions/$username|$username]] has '''$wikiPoints''' WikiPoints."
				);
			}
		}
	}

	public function trySubmit( array $formData ): bool {
		$out = $this->getOutput();
		$out->redirect( $this->getPageTitle( $formData[ 'username' ] )->getLocalURL() );
		return true;
	}

	private function calculateWikiPoints( int $userID ): int {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		return $dbr->newSelectQueryBuilder()
			->select( [ 'wiki_points' => 'SUM( r.rev_len - COALESCE( p.rev_len, 0 ) )' ] )
			->from( 'revision', 'r' )
			->leftJoin( 'revision', 'p', 'r.rev_parent_id = p.rev_id' )
			->where( [ 'r.rev_actor' => $userID ] )
			->caller( __METHOD__ )
			->fetchRow()->wiki_points;
	}
}
