<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapType;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;

final class Legacy1_17PacketCodecTest extends TestCase{
	private static function encode(DataPacket $packet, int $protocolId) : string{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);
		return $out->getData();
	}

	public function testResourcePackForceFlagAppearsIn1_17_10() : void{
		$packet = new ResourcePacksInfoPacket();
		$old = self::encode($packet, ProtocolInfo::PROTOCOL_1_17_0);
		$new = self::encode($packet, ProtocolInfo::PROTOCOL_1_17_10);
		self::assertSame(strlen($old) + 1, strlen($new));
		$decoded = new ResourcePacksInfoPacket();
		$decoded->decode(new ByteBufferReader($old), ProtocolInfo::PROTOCOL_1_17_0);
		self::assertFalse($decoded->forceServerPacks);
	}

	public function testTitleXboxFieldsAppearIn1_17_10() : void{
		$packet = SetTitlePacket::title('legacy');
		$old = self::encode($packet, ProtocolInfo::PROTOCOL_1_17_0);
		$new = self::encode($packet, ProtocolInfo::PROTOCOL_1_17_10);
		self::assertSame(strlen($old) + 2, strlen($new));
		$decoded = new SetTitlePacket();
		$decoded->decode(new ByteBufferReader($old), ProtocolInfo::PROTOCOL_1_17_0);
		self::assertSame('legacy', $decoded->text);
	}

	public function test1_17_40SubChunkResponseOmitsBlobHashFlag() : void{
		$entry = new SubChunkPacketEntry(new SubChunkPositionOffset(0, 0, 0), SubChunkRequestResult::SUCCESS, '', SubChunkPacketHeightMapType::NO_DATA, null, SubChunkPacketHeightMapType::ALL_COPIED, null, null);
		$packet = SubChunkPacket::create(false, 0, new SubChunkPosition(1, 0, 2), [$entry]);
		$old = self::encode($packet, ProtocolInfo::PROTOCOL_1_17_40);
		$new = self::encode($packet, ProtocolInfo::PROTOCOL_1_18_0);
		self::assertSame(strlen($old) + 1, strlen($new));
		$decoded = new SubChunkPacket();
		$decoded->decode(new ByteBufferReader($old), ProtocolInfo::PROTOCOL_1_17_40);
		self::assertSame(1, $decoded->getBaseSubChunkPosition()->getX());
	}
}
