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
		$res = $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'wikipoints', 'top-20' ),
			// 1 hour
			3600,
			fn () => $this->fetchTop20()
		);
		$tableHeader = Html::element( 'th', [], $this->msg( 'wikipoints-mostpoints-rank' )->text() )
		. Html::element( 'th', [], $this->msg( 'wikipoints-mostpoints-username' )->text() )
		. Html::element( 'th', [], $this->msg( 'wikipoints-mostpoints-wikipoints' )->text() );
		$out->addHTML( Html::openElement( 'table', [ 'class' => 'wikitable' ] ) );
		$out->addHTML( Html::rawElement( 'tr', [], $tableHeader ) );
		$i = 1;
		$lang = $this->getLanguage();
		foreach ( $res as $row ) {
			$title = $this->specialPageFactory->getPage( 'Contributions' )->getPageTitle( $row['name'] );
			$tableRow = Html::element( 'td', [], $lang->formatNum( $i ) )
			. Html::rawElement( 'td', [], $this->linkRenderer->makeLink( $title, $row['name'] ) )
			. Html::element( 'td', [], $lang->formatNum( $row['points'] ) );
			$out->addHTML( Html::rawElement( 'tr', [], $tableRow ) );
			$i++;
		}
		$out->addHTML( Html::closeElement( 'table' ) );
	}

	/**
	 * @inheritDoc
	 */
	private function fetchTop20() {
		$dbr = $this->connectionProvider->getReplicaDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'actor', 'name', 'points' ] )
			->from( 'wikipoints' )
			->where( [ 'blocked' => 0 ] )
			->orderBy( 'points', 'DESC' )
			->limit( 20 )
			->caller( __METHOD__ )
			->fetchResultSet();
		$users = [];
		foreach ( $res as $row ) {
			$users[] = [
				'actor' => $row->actor,
				'name' => $row->name,
				'points' => $row->points,
			];
		}
		return $users;
	}
}
