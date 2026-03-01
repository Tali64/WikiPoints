<?php
namespace MediaWiki\Extension\WikiPoints;

class Installer {
	/**
	 * @inheritDoc
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'wikipoints', __DIR__ . '/wikipoints.sql' );
	}
}
