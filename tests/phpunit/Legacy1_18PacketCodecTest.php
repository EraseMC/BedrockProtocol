<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;

/** Wire-format regression tests for the stable Minecraft 1.18 boundaries. */
final class Legacy1_18PacketCodecTest extends TestCase{
	private static function encode(DataPacket $packet, int $protocolId) : string{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);
		return $out->getData();
	}

	public function testSubChunkRequestAddsOffsetListIn1_18_10() : void{
		$packet = SubChunkRequestPacket::create(0, new SubChunkPosition(1, 2, 3), [new SubChunkPositionOffset(1, -1, 2)]);

		self::assertSame(hex2bin('af0100020406'), self::encode($packet, ProtocolInfo::PROTOCOL_1_18_0));
		self::assertSame(hex2bin('af01000204060100000001ff02'), self::encode($packet, ProtocolInfo::PROTOCOL_1_18_10));

		$decoded = new SubChunkRequestPacket();
		$decoded->decode(new ByteBufferReader(hex2bin('af0100020406')), ProtocolInfo::PROTOCOL_1_18_0);
		self::assertSame([], $decoded->getEntries());
	}

	public function testCommandArgumentIdsChangeIn1_18_30() : void{
		self::assertSame(0x20, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_18_10, AvailableCommandsPacket::ARG_TYPE_STRING));
		self::assertSame(0x26, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_18_30, AvailableCommandsPacket::ARG_TYPE_STRING));
		self::assertSame(0x28, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_18_10, AvailableCommandsPacket::ARG_TYPE_POSITION));
		self::assertSame(0x2f, AvailableCommandsPacket::convertArg(ProtocolInfo::PROTOCOL_1_18_30, AvailableCommandsPacket::ARG_TYPE_POSITION));
	}
}
