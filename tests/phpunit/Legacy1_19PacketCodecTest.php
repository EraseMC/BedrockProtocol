<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerUIIds;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\recipe\IntIdMetaItemDescriptor;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;

/**
 * Wire-format regression tests for stable 1.19 protocol boundaries.
 *
 * The expected byte strings intentionally cover the field that was added or
 * removed at each boundary; an encode/decode round-trip on its own cannot
 * detect a mutually wrong encoder and decoder.
 */
final class Legacy1_19PacketCodecTest extends TestCase{

	private static function encode(DataPacket $packet, int $protocolId) : string{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);
		return $out->getData();
	}

	public function testMapInfoClientPixelsBeginIn1_19_20() : void{
		$packet = MapInfoRequestPacket::create(123, []);

		self::assertSame(hex2bin('44f601'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_10));
		self::assertSame(hex2bin('44f60100000000'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_20));

		$decoded = new MapInfoRequestPacket();
		$decoded->decode(new ByteBufferReader(hex2bin('44f601')), ProtocolInfo::PROTOCOL_1_19_10);
		self::assertSame(123, $decoded->mapId);
		self::assertSame([], $decoded->clientPixels);
	}

	public function testModalFormResponseChangesToOptionalFieldsIn1_19_20() : void{
		$packet = ModalFormResponsePacket::response(7, '[]');

		self::assertSame(hex2bin('6507025b5d'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_10));
		self::assertSame(hex2bin('650701025b5d00'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_20));

		$decoded = new ModalFormResponsePacket();
		$decoded->decode(new ByteBufferReader(hex2bin('6507025b5d')), ProtocolInfo::PROTOCOL_1_19_10);
		self::assertSame(7, $decoded->formId);
		self::assertSame('[]', $decoded->formData);
		self::assertNull($decoded->cancelReason);
	}

	public function testModalFormCancellationIsRejectedBefore1_19_20() : void{
		$this->expectException(\InvalidArgumentException::class);
		self::encode(ModalFormResponsePacket::cancel(7, ModalFormResponsePacket::CANCEL_REASON_CLOSED), ProtocolInfo::PROTOCOL_1_19_10);
	}

	public function testNetworkSettingsAddsAlgorithmAndThrottlingIn1_19_30() : void{
		$packet = NetworkSettingsPacket::create(512, CompressionAlgorithm::ZLIB, false, 5, 0.5);

		self::assertSame(hex2bin('8f010002'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_20));
		self::assertSame(hex2bin('8f010002000000050000003f'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_30));

		$decoded = new NetworkSettingsPacket();
		$decoded->decode(new ByteBufferReader(hex2bin('8f010002')), ProtocolInfo::PROTOCOL_1_19_20);
		self::assertSame(512, $decoded->getCompressionThreshold());
		self::assertSame(CompressionAlgorithm::ZLIB, $decoded->getCompressionAlgorithm());
		self::assertFalse($decoded->isEnableClientThrottling());
	}

	public function testChunkPublisherSavedChunksBeginIn1_19_20() : void{
		$packet = NetworkChunkPublisherUpdatePacket::create(new BlockPosition(1, 2, 3), 4, []);

		self::assertSame(hex2bin('7902040604'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_10));
		self::assertSame(hex2bin('790204060400000000'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_20));
	}

	public function testRequestChunkRadiusAddsMaximumIn1_19_80() : void{
		$packet = RequestChunkRadiusPacket::create(8, 28);

		self::assertSame(hex2bin('4510'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_70));
		self::assertSame(hex2bin('45101c'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_80));

		$decoded = new RequestChunkRadiusPacket();
		$decoded->decode(new ByteBufferReader(hex2bin('4510')), ProtocolInfo::PROTOCOL_1_19_70);
		self::assertSame(8, $decoded->radius);
		self::assertSame(0, $decoded->maxRadius);
	}

	public function testAdventureSettingsUsesLegacyFixedLittleEndianActorId() : void{
		$packet = AdventureSettingsPacket::create(1, 2, 3, 4, 5, -2);
		self::assertSame(hex2bin('370102030405feffffffffffffff'), self::encode($packet, ProtocolInfo::PROTOCOL_1_19_0));

		$decoded = new AdventureSettingsPacket();
		$decoded->decode(new ByteBufferReader(hex2bin('370102030405feffffffffffffff')), ProtocolInfo::PROTOCOL_1_19_0);
		self::assertSame(-2, $decoded->targetActorUniqueId);

		$flags = AdventureSettingsPacket::create(0, 0, 0, 0, 0, 0);
		$flags->setFlag(AdventureSettingsPacket::MINE, true);
		self::assertSame(1, $flags->flags2);
		self::assertTrue($flags->getFlag(AdventureSettingsPacket::MINE));
	}

	public function testRecipeIngredientsUseThePre1_19_30IntegerFormat() : void{
		$ingredient = new RecipeIngredient(new IntIdMetaItemDescriptor(2, 3), 4);

		$old = new ByteBufferWriter();
		CommonTypes::putRecipeIngredient($old, ProtocolInfo::PROTOCOL_1_19_21, $ingredient);
		self::assertSame(hex2bin('040608'), $old->getData());

		$new = new ByteBufferWriter();
		CommonTypes::putRecipeIngredient($new, ProtocolInfo::PROTOCOL_1_19_30, $ingredient);
		self::assertSame(hex2bin('010200030008'), $new->getData());

		$decoded = CommonTypes::getRecipeIngredient(new ByteBufferReader(hex2bin('040608')), ProtocolInfo::PROTOCOL_1_19_21);
		self::assertInstanceOf(IntIdMetaItemDescriptor::class, $decoded->getDescriptor());
		self::assertSame(4, $decoded->getCount());

		$air = new ByteBufferWriter();
		CommonTypes::putRecipeIngredient($air, ProtocolInfo::PROTOCOL_1_19_21, new RecipeIngredient(new IntIdMetaItemDescriptor(0, 0), 64));
		self::assertSame(hex2bin('00'), $air->getData());
	}

	public function testContainerIdsShiftBefore1_19_50() : void{
		$container = new FullContainerName(ContainerUIIds::ENCHANTING_INPUT);
		$old = new ByteBufferWriter();
		$container->write($old, ProtocolInfo::PROTOCOL_1_19_40);
		self::assertSame(hex2bin('15'), $old->getData());
		self::assertSame(ContainerUIIds::ENCHANTING_INPUT, FullContainerName::read(new ByteBufferReader($old->getData()), ProtocolInfo::PROTOCOL_1_19_40)->getContainerId());

		$new = new ByteBufferWriter();
		$container->write($new, ProtocolInfo::PROTOCOL_1_19_50);
		self::assertSame(hex2bin('16'), $new->getData());
		self::assertSame(ContainerUIIds::ENCHANTING_INPUT, FullContainerName::read(new ByteBufferReader($new->getData()), ProtocolInfo::PROTOCOL_1_19_50)->getContainerId());
	}

	public function testEntityFlagsCrossThe64BitBoundaryBefore1_19_50() : void{
		$metadata = [
			EntityMetadataProperties::FLAGS => new LongMetadataProperty((1 << EntityMetadataFlags::CAN_POWER_JUMP) | (1 << EntityMetadataFlags::CAN_DASH) | (1 << EntityMetadataFlags::LINGER)),
			EntityMetadataProperties::FLAGS2 => new LongMetadataProperty(1),
		];
		$legacy = EntityMetadataFlags::encode($metadata, ProtocolInfo::PROTOCOL_1_19_40);
		self::assertSame((1 << EntityMetadataFlags::CAN_POWER_JUMP) | (1 << EntityMetadataFlags::CAN_DASH) | (1 << 63), $legacy[EntityMetadataProperties::FLAGS]->getValue());
		self::assertSame(0, $legacy[EntityMetadataProperties::FLAGS2]->getValue());
		$decoded = EntityMetadataFlags::decode($legacy, ProtocolInfo::PROTOCOL_1_19_40);
		self::assertSame((1 << EntityMetadataFlags::CAN_POWER_JUMP) | (1 << EntityMetadataFlags::LINGER), $decoded[EntityMetadataProperties::FLAGS]->getValue());
		self::assertSame(1, $decoded[EntityMetadataProperties::FLAGS2]->getValue());
		self::assertSame($metadata, EntityMetadataFlags::encode($metadata, ProtocolInfo::PROTOCOL_1_19_50));
	}
}
