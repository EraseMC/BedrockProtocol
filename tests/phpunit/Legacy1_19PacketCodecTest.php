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
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CompressionAlgorithm;

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
}
