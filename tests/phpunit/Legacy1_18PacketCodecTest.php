<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapType;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;

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

	public function test1_18_0SubChunkResponseRetainsAbsolutePosition() : void{
		$position = new SubChunkPosition(1, -4, 2);
		$entry = new SubChunkPacketEntry(new SubChunkPositionOffset(0, 0, 0), SubChunkRequestResult::SUCCESS, '', SubChunkPacketHeightMapType::NO_DATA, null, SubChunkPacketHeightMapType::ALL_COPIED, null, null);
		$packet = SubChunkPacket::create(false, 0, $position, [$entry]);
		$wire = hex2bin('ae010002070400020000');
		self::assertSame($wire, self::encode($packet, ProtocolInfo::PROTOCOL_1_18_0));

		$decoded = new SubChunkPacket();
		$decoded->decode(new ByteBufferReader($wire), ProtocolInfo::PROTOCOL_1_18_0);
		self::assertFalse($decoded->isCacheEnabled());
		self::assertSame(-4, $decoded->getBaseSubChunkPosition()->getY());
		self::assertSame(1, $decoded->getBaseSubChunkPosition()->getX());
		self::assertSame(2, $decoded->getBaseSubChunkPosition()->getZ());
	}

	public function testPlayerAuthInputHasNoInteractionModeBefore1_19() : void{
		// 1.18 has pitch/yaw/position/move/head (8 floats), flags/modes/tick (4 varints), then delta (3 floats).
		$flags = str_repeat("\x80", 9) . "\x00";
		$legacyWire = hex2bin('9001') . str_repeat("\x00", 32) . $flags . str_repeat("\x00", 3) . str_repeat("\x00", 12);
		$packet = new PlayerAuthInputPacket();
		$packet->decode(new ByteBufferReader($legacyWire), ProtocolInfo::PROTOCOL_1_18_0);
		self::assertSame($legacyWire, self::encode($packet, ProtocolInfo::PROTOCOL_1_18_0));
		self::assertSame(0, $packet->getTick());

		$modernWire = hex2bin('9001') . str_repeat("\x00", 32) . $flags . str_repeat("\x00", 4) . str_repeat("\x00", 12);
		$modernPacket = new PlayerAuthInputPacket();
		$modernPacket->decode(new ByteBufferReader($modernWire), ProtocolInfo::PROTOCOL_1_19_0);
		self::assertSame($modernWire, self::encode($modernPacket, ProtocolInfo::PROTOCOL_1_19_0));
	}

	public function testPlayerActionHasNoResultPositionBefore1_19() : void{
		$legacyWire = hex2bin('24010002460402');
		$packet = new PlayerActionPacket();
		$packet->decode(new ByteBufferReader($legacyWire), ProtocolInfo::PROTOCOL_1_18_0);
		self::assertSame($legacyWire, self::encode($packet, ProtocolInfo::PROTOCOL_1_18_0));
		self::assertTrue($packet->blockPosition->equals($packet->resultPosition));

		$modernWire = hex2bin('24010002460402460402');
		$modernPacket = new PlayerActionPacket();
		$modernPacket->decode(new ByteBufferReader($modernWire), ProtocolInfo::PROTOCOL_1_19_0);
		self::assertSame($modernWire, self::encode($modernPacket, ProtocolInfo::PROTOCOL_1_19_0));
	}
}
