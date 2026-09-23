<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapType;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingNonImplementedStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;

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

	public function test1_17AuthInputParsesFlaggedBlockActions() : void{
		$out = new ByteBufferWriter();
		$out->writeByteArray(hex2bin('9001') . str_repeat("\x00", 32));
		VarInt::writeUnsignedLong($out, 1 << PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS);
		$out->writeByteArray(str_repeat("\x00", 3 + 12)); // input mode, play mode, tick, delta
		$out->writeByteArray(hex2bin('020000000000')); // one START_BREAK action at 0,0,0 face 0
		$legacyWire = $out->getData();
		$packet = new PlayerAuthInputPacket();
		$packet->decode(new ByteBufferReader($legacyWire), ProtocolInfo::PROTOCOL_1_17_0);
		self::assertTrue($packet->getInputFlags()->get(PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS));
		self::assertCount(1, $packet->getBlockActions());
		self::assertSame(0, $packet->getBlockActions()[0]->getActionType());
		$roundTrip = new PlayerAuthInputPacket();
		$roundTrip->decode(new ByteBufferReader(self::encode($packet, ProtocolInfo::PROTOCOL_1_17_0)), ProtocolInfo::PROTOCOL_1_17_0);
		self::assertCount(1, $roundTrip->getBlockActions());
	}

	public function test1_17DeprecatedCraftingActionIds() : void{
		foreach([ProtocolInfo::PROTOCOL_1_17_0, ProtocolInfo::PROTOCOL_1_17_10, ProtocolInfo::PROTOCOL_1_17_30, ProtocolInfo::PROTOCOL_1_17_40] as $protocolId){
			foreach([
				'02010e00' => DeprecatedCraftingNonImplementedStackRequestAction::class,
				'02010f000000' => DeprecatedCraftingResultsStackRequestAction::class,
			] as $hex => $expectedClass){
				$wire = hex2bin($hex);
				$decoded = ItemStackRequest::read(new ByteBufferReader($wire), $protocolId);
				self::assertInstanceOf($expectedClass, $decoded->getActions()[0]);
				$out = new ByteBufferWriter();
				$decoded->write($out, $protocolId);
				self::assertSame($wire, $out->getData());
			}
		}
	}
}
