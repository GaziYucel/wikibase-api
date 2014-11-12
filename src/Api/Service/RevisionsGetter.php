<?php

namespace Wikibase\Api\Service;

use DataValues\Serializers\DataValueSerializer;
use Deserializers\Deserializer;
use Guzzle\Common\Exception\InvalidArgumentException;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\DataModel\Revision;
use Mediawiki\DataModel\Revisions;
use RuntimeException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\ItemContent;
use Wikibase\DataModel\PropertyContent;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\SiteLink;

/**
 * @author Adam Shorland
 */
class RevisionsGetter {

	/**
	 * @var MediawikiApi
	 */
	protected $api;

	/**
	 * @var SerializerFactory
	 */
	protected $serializerFactory;

	/**
	 * @var Deserializer
	 */
	private $entityDeserializer;

	/**
	 * @param MediawikiApi $api
	 * @param Deserializer $entityDeserializer
	 */
	public function __construct( MediawikiApi $api, Deserializer $entityDeserializer ) {
		$this->api = $api;
		$this->entityDeserializer = $entityDeserializer;
		$this->serializerFactory =  new SerializerFactory(
			new DataValueSerializer()
		);
	}

	/**
	 * Get revisions for the entities identified using as few requests as possible.
	 *
	 * @param array $identifyingInfoArray Can include the following (EntityId,SiteLink)
	 *
	 * @since 0.4
	 *
	 * @return Revisions
	 */
	public function getRevisions( array $identifyingInfoArray ) {
		$entityIdStrings = array();
		$siteLinksStringMapping = array();

		foreach( $identifyingInfoArray as $someInfo ) {
			if( $someInfo instanceof EntityId ) {
				$entityIdStrings[] = $someInfo->getSerialization();
			} elseif ( $someInfo instanceof SiteLink ) {
				$siteLinksStringMapping[ $someInfo->getSiteId() ][] = $someInfo->getPageName();
			} else {
				throw new InvalidArgumentException( 'Unexpected $identifyingInfoArray in RevisionsGetter::getRevisions' );
			}
		}

		// The below makes as few requests as possible to get the Revisions required!
		$gotRevisionsFromIds = false;
		$revisions = new Revisions();
		if( !empty( $siteLinksStringMapping ) ) {
			foreach( $siteLinksStringMapping as $site => $siteLinkStrings ) {
				$params = array( 'sites' => $site );
				if( !$gotRevisionsFromIds && !empty( $entityIdStrings ) ) {
					$params['ids'] = implode( '|', $entityIdStrings );
					$gotRevisionsFromIds = true;
				}
				$params['titles'] = implode( '|', $siteLinkStrings );
				$result = $this->api->getAction( 'wbgetentities', $params );
				$resultRevisions = $this->newRevisionsFromResult( $result['entities'] );
				$revisions->addRevisions( $resultRevisions );

			}
		} else {
			$params = array( 'ids' => implode( '|', $entityIdStrings ) );
			$result = $this->api->getAction( 'wbgetentities', $params );
			$resultRevisions = $this->newRevisionsFromResult( $result['entities'] );
			$revisions->addRevisions( $resultRevisions );
		}

		return $revisions;
	}

	/**
	 * @param array $entitiesResult
	 * @returns Revisions
	 * @todo this could be factored into a different class?
	 */
	private function newRevisionsFromResult( array $entitiesResult ) {
		$revisions = new Revisions();
		foreach( $entitiesResult as $entityResult ) {
			if( array_key_exists( 'missing', $entityResult ) ) {
				continue;
			}
			$revisions->addRevision( new Revision(
				$this->getContentFromEntity( $this->entityDeserializer->deserialize( $entityResult ) ),
				$entityResult['pageid'],
				$entityResult['lastrevid'],
				null,
				null,
				$entityResult['modified']
			) );
		}
		return $revisions;
	}

	/**
	 * @param Item|Property $entity
	 *
	 * @throws RuntimeException
	 * @return ItemContent|PropertyContent
	 * @todo this could be factored into a different class?
	 */
	private function getContentFromEntity( $entity ) {
		switch ( $entity->getType() ) {
			case Item::ENTITY_TYPE:
				return new ItemContent( $entity );
			case Property::ENTITY_TYPE:
				return new PropertyContent( $entity );
			default:
				throw new RuntimeException( 'I cant get a content for this type of entity' );
		}
	}

} 