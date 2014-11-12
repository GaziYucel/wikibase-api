<?php

namespace Wikibase\Api\Service;

use Mediawiki\Api\MediawikiApi;
use UnexpectedValueException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\SiteLink;

/**
 * @author Adam Shorland
 */
class SiteLinkSetter {

	/**
	 * @var MediawikiApi
	 */
	private $api;

	/**
	 * @param MediawikiApi $api
	 */
	public function __construct( MediawikiApi $api ) {
		$this->api = $api;
	}

	/**
	 * @since 0.2
	 *
	 * @param SiteLink $siteLink
	 * @param EntityId|Item|Property|SiteLink $target
	 *
	 * @return bool
	 */
	public function set( SiteLink $siteLink, $target ) {
		$this->throwExceptionsOnBadTarget( $target );

		$params = $this->getTargetParamsFromTarget(
			$this->getEntityIdentifierFromTarget( $target )
		);

		$params['linksite'] = $siteLink->getSiteId();
		$params['linktitle'] = $siteLink->getPageName();

		$params['token'] = $this->api->getToken();
		$this->api->postAction( 'wbsetsitelink', $params );
		return true;
	}

	/**
	 * @param mixed $target
	 *
	 * @throws UnexpectedValueException
	 *
	 * @todo Fix duplicated code
	 */
	private function throwExceptionsOnBadTarget( $target ) {
		if( !$target instanceof EntityId && !$target instanceof Item && !$target instanceof Property && ! $target instanceof SiteLink ) {
			throw new UnexpectedValueException( '$target needs to be an EntityId, Item, Property or SiteLink' );
		}
		if( ( $target instanceof Item || $target instanceof Property ) && is_null( $target->getId() ) ) {
			throw new UnexpectedValueException( '$target Entity object needs to have an Id set' );
		}
	}

	/**
	 * @param EntityId|Item|Property $target
	 *
	 * @throws UnexpectedValueException
	 * @return EntityId|SiteLink
	 *
	 * @todo Fix duplicated code
	 */
	private function getEntityIdentifierFromTarget( $target ) {
		if ( $target instanceof Item || $target instanceof Property ) {
			return $target->getId();
		} else {
			return $target;
		}
	}

	/**
	 * @param EntityId|SiteLink $target
	 *
	 * @throws UnexpectedValueException
	 * @return array
	 *
	 * @todo Fix duplicated code
	 */
	private function getTargetParamsFromTarget( $target ) {
		if( $target instanceof EntityId ) {
			return array( 'id' => $target->getSerialization() );
		} elseif( $target instanceof SiteLink ) {
			return array(
				'site' => $target->getSiteId(),
				'title' => $target->getPageName(),
			);
		} else {
			throw new UnexpectedValueException( '$target needs to be an EntityId or SiteLink' );
		}
	}
} 