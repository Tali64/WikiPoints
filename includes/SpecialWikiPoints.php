<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialWikiPoints extends SpecialPage {
	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly LinkRenderer $linkRenderer,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserFactory $userFactory,
		private readonly WANObjectCache $cache,
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
			->setSubmitTextMsg( 'wikipoints-special-form-name-submit' )
			->setSubmitCallback( [ $this, 'trySubmit' ] )
			->show();

		if ( $subPage ) {
			$username = str_replace( '_', ' ', $subPage );
			$userID = $this->userFactory->newFromName( $username )->getActorId();
			if ( $userID == 0 ) {
				$out->addWikiMsg( 'wikipoints-user-nonexistent', $username );
			} else {
				$lang = $this->getLanguage();
				$wikiPoints = $lang->formatNum( $this->getWikiPoints( $userID ) );
				$contributions = $this->specialPageFactory->getPage( 'Contributions' )->getPageTitle( $username );
				$out->addHTML(
					$this->msg( 'wikipoints-user-has' )
						->rawParams( $this->linkRenderer->makeLink( $contributions, $username ) )
						->params( $wikiPoints )
						->parse()
				);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function trySubmit( array $formData ): bool {
		$out = $this->getOutput();
		$out->redirect( $this->getPageTitle( $formData[ 'username' ] )->getLocalURL() );
		return true;
	}

	/**
	 * @inheritDoc
	 */
	private function getWikiPoints( int $userID ): int {
		$wikiPoints = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'wikipoints', 'user-points', $userID ),
			// 1 hour
			3600,
			fn () => $this->fetchWikiPointsFromDB( $userID )
		);
		return $wikiPoints;
	}

	/**
	 * @inheritDoc
	 */
	private function fetchWikiPointsFromDB( int $userID ): int {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		return $dbr->newSelectQueryBuilder()
			->select( 'points' )
			->from( 'wikipoints' )
			->where( [ 'actor' => $userID ] )
			->caller( __METHOD__ )
			->fetchRow()
			->points ?? 0;
	}
}
