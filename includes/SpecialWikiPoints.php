<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialWikiPoints extends SpecialPage {
	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly LinkRenderer $linkRenderer,
		private readonly SpecialPageFactory $specialPageFactory,
		private readonly UserFactory $userFactory,
	) {
		parent::__construct( 'WikiPoints' );
		$this->cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
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
			->setSubmitText( $this->msg( 'wikipoints-special-form-name-submit' ) )
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

	public function trySubmit( array $formData ): bool {
		$out = $this->getOutput();
		$out->redirect( $this->getPageTitle( $formData[ 'username' ] )->getLocalURL() );
		return true;
	}

	private function getWikiPoints( int $userID ): int {
		$wikiPoints = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'wikipoints', 'user-points', $userID ),
			// 10 minutes
			600,
			function () use ( $userID ) {
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
		);
		return $wikiPoints;
	}
}
