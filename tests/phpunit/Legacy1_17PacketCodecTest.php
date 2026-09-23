<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketEntry;
use pocketmine\network\mcpe\protocol\types\SubChunkPacketHeightMapType;
use pocketmine\network\mcpe\protocol\types\SubChunkPosition;
use pocketmine\network\mcpe\protocol\types\SubChunkPositionOffset;
use pocketmine\network\mcpe\protocol\types\SubChunkRequestResult;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingNonImplementedStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;

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

	public function test1_17InteractionDataPrecedesBlockActions() : void{
		$protocolId = ProtocolInfo::PROTOCOL_1_17_0;
		$origin = new BlockPosition(0, 0, 0);
		$out = new ByteBufferWriter();
		$out->writeByteArray(hex2bin('9001') . str_repeat("\x00", 32));
		VarInt::writeUnsignedLong($out, (1 << PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION) | (1 << PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS));
		$out->writeByteArray(str_repeat("\x00", 3 + 12)); // input mode, play mode, tick, delta
		VarInt::writeSignedInt($out, 0); // interaction request ID
		VarInt::writeUnsignedInt($out, 0); // inventory action count
		VarInt::writeUnsignedInt($out, UseItemTransactionData::ACTION_BREAK_BLOCK);
		CommonTypes::putBlockPosition($out, $origin);
		VarInt::writeSignedInt($out, 0); // face
		VarInt::writeSignedInt($out, 0); // hotbar slot
		CommonTypes::putItemStackWrapper($out, $protocolId, ItemStackWrapper::legacy(ItemStack::null()), false);
		CommonTypes::putVector3($out, new Vector3(0, 0, 0)); // player position
		CommonTypes::putVector3($out, new Vector3(0, 0, 0)); // click position
		VarInt::writeUnsignedInt($out, 0); // block runtime ID
		VarInt::writeSignedInt($out, 1); // one block action
		VarInt::writeSignedInt($out, 0); // START_BREAK
		CommonTypes::putBlockPosition($out, $origin);
		VarInt::writeSignedInt($out, 0); // face

		$packet = new PlayerAuthInputPacket();
		$packet->decode(new ByteBufferReader($out->getData()), $protocolId);
		self::assertSame(UseItemTransactionData::ACTION_BREAK_BLOCK, $packet->getItemInteractionData()?->getTransactionData()->getActionType());
		self::assertCount(1, $packet->getBlockActions());
		self::assertSame(0, $packet->getBlockActions()[0]->getActionType());
	}

	public function testPre1_17_30SkinLayout() : void{
		$empty = new SkinImage(0, 0, '');
		$skin = new SkinData('id', 'pf', 'patch', $empty, [], $empty, 'geo', '1.17.0', 'anim', 'cape', 'full', premium: true, persona: false, personaCapeOnClassic: true);
		$encode = static function(int $protocolId) use ($skin) : string{
			$out = new ByteBufferWriter();
			CommonTypes::putSkin($out, $protocolId, $skin);
			return $out->getData();
		};

		// 1.17.0/1.17.10: no engine version; premium/persona/cape-on-classic follow the animation data
		foreach([ProtocolInfo::PROTOCOL_1_17_0, ProtocolInfo::PROTOCOL_1_17_10] as $protocolId){
			$old = $encode($protocolId);
			self::assertStringContainsString("\x03geo\x04anim\x01\x00\x01\x04cape\x04full", $old);
			$decoded = CommonTypes::getSkin($reader = new ByteBufferReader($old), $protocolId);
			self::assertSame(0, $reader->getUnreadLength());
			self::assertSame('anim', $decoded->getAnimationData());
			self::assertSame('cape', $decoded->getCapeId());
			self::assertTrue($decoded->isPremium());
			self::assertFalse($decoded->isPersona());
			self::assertTrue($decoded->isPersonaCapeOnClassic());
		}

		// 1.17.30: engine version added; flags plus isPrimaryUser moved to the end
		$new = $encode(ProtocolInfo::PROTOCOL_1_17_30);
		self::assertStringContainsString("\x03geo\x061.17.0\x04anim\x04cape\x04full", $new);
		self::assertStringEndsWith("\x01\x00\x01\x01", $new);
		self::assertSame(strlen($encode(ProtocolInfo::PROTOCOL_1_17_0)) + 8, strlen($new));
	}

	public function testPre1_17_30OmitsLaterPacketFields() : void{
		$hurt = HurtArmorPacket::create(1, 2, 3);
		self::assertSame(strlen(self::encode($hurt, ProtocolInfo::PROTOCOL_1_17_10)) + 1, strlen(self::encode($hurt, ProtocolInfo::PROTOCOL_1_17_30)));

		$pick = ActorPickRequestPacket::create(5, 1, true);
		$old = self::encode($pick, ProtocolInfo::PROTOCOL_1_17_10);
		self::assertSame(strlen($old) + 1, strlen(self::encode($pick, ProtocolInfo::PROTOCOL_1_17_30)));
		$decoded = new ActorPickRequestPacket();
		$decoded->decode($reader = new ByteBufferReader($old), ProtocolInfo::PROTOCOL_1_17_10);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertFalse($decoded->addUserData);
	}
}
