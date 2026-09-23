<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use PHPUnit\Framework\TestCase;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\VarInt;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\serializer\NetworkNbtSerializer;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\inventory\FullContainerName;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraData;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraDataShield;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\NormalTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CraftRecipeStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\CreativeCreateStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\DeprecatedCraftingResultsStackRequestAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\inventory\stackresponse\ItemStackResponse;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\network\mcpe\protocol\types\LevelSettings;
use pocketmine\network\mcpe\protocol\types\NetworkPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\ServerAuthMovementMode;
use pocketmine\network\mcpe\protocol\types\ServerTelemetryData;
use pocketmine\network\mcpe\protocol\types\skin\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use pocketmine\network\mcpe\protocol\types\SpawnSettings;
use Ramsey\Uuid\Uuid;

final class Legacy1_16PacketCodecTest extends TestCase{
	private static function encode(DataPacket $packet, int $protocolId) : string{
		$out = new ByteBufferWriter();
		$packet->encode($out, $protocolId);
		return $out->getData();
	}

	/**
	 * @param string[] $canPlaceOn
	 * @param string[] $canDestroy
	 */
	private static function extraData(?CompoundTag $nbt, array $canPlaceOn = [], array $canDestroy = []) : string{
		$out = new ByteBufferWriter();
		(new ItemStackExtraData($nbt, $canPlaceOn, $canDestroy))->write($out);
		return $out->getData();
	}

	public function testLegacyItemStackLayout() : void{
		$nbt = CompoundTag::create()->setString("Name", "x");
		$stack = new ItemStack(5, 3, 64, 0, self::extraData($nbt, ["minecraft:stone"], []));

		$out = new ByteBufferWriter();
		CommonTypes::putItemStackWithoutStackId($out, ProtocolInfo::PROTOCOL_1_16_210, $stack);
		$wire = $out->getData();

		$expected = new ByteBufferWriter();
		VarInt::writeSignedInt($expected, 5);
		VarInt::writeSignedInt($expected, (3 << 8) | 64);
		$expected->writeByteArray("\xff\xff\x01" . (new NetworkNbtSerializer())->write(new TreeRoot($nbt)));
		VarInt::writeSignedInt($expected, 1);
		CommonTypes::putString($expected, "minecraft:stone");
		VarInt::writeSignedInt($expected, 0);
		self::assertSame($expected->getData(), $wire);

		$decoded = CommonTypes::getItemStackWithoutStackId($reader = new ByteBufferReader($wire), ProtocolInfo::PROTOCOL_1_16_210);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertSame([5, 3, 64], [$decoded->getId(), $decoded->getMeta(), $decoded->getCount()]);
		self::assertSame($stack->getRawExtraData(), $decoded->getRawExtraData());
	}

	public function testLegacyShieldCarriesBlockingTick() : void{
		foreach([ProtocolInfo::PROTOCOL_1_16_0 => 513, ProtocolInfo::PROTOCOL_1_16_100 => 355] as $protocolId => $shieldId){
			$extra = new ByteBufferWriter();
			(new ItemStackExtraDataShield(null, [], [], 7))->write($extra);
			$stack = new ItemStack($shieldId, 0, 1, 0, $extra->getData());
			$out = new ByteBufferWriter();
			CommonTypes::putItemStackWithoutStackId($out, $protocolId, $stack);
			self::assertStringEndsWith("\x00\x00\x00\x00\x0e", $out->getData()); //no NBT, no lists, varlong 7
			$decoded = CommonTypes::getItemStackWithoutStackId($reader = new ByteBufferReader($out->getData()), $protocolId);
			self::assertSame(0, $reader->getUnreadLength());
			self::assertSame($stack->getRawExtraData(), $decoded->getRawExtraData());
		}
	}

	public function testInventoryContentCarriesLegacyStackId() : void{
		$item = new ItemStackWrapper(9, new ItemStack(1, 0, 1, 0, self::extraData(null)));
		$packet = InventoryContentPacket::create(0, [$item], new FullContainerName(0), 0, new ItemStackWrapper(0, ItemStack::null()));
		$wire = self::encode($packet, ProtocolInfo::PROTOCOL_1_16_100);
		//packet ID, window ID, count, then the zigzag stack ID before the legacy item
		self::assertSame("\x31\x00\x01\x12\x02\x02\x00\x00\x00\x00", $wire);
		$decoded = new InventoryContentPacket();
		$decoded->decode(new ByteBufferReader($wire), ProtocolInfo::PROTOCOL_1_16_100);
		self::assertSame(9, $decoded->items[0]->getStackId());
	}

	public function testInventoryTransactionItemStackIds() : void{
		$action = new NetworkInventoryAction();
		$action->sourceType = NetworkInventoryAction::SOURCE_CONTAINER;
		$action->windowId = 0;
		$action->inventorySlot = 1;
		$action->oldItem = new ItemStackWrapper(0, ItemStack::null());
		$action->newItem = new ItemStackWrapper(0, ItemStack::null());
		$action->legacyNewItemStackId = 4;
		$packet = InventoryTransactionPacket::create(0, [], NormalTransactionData::new([$action]));
		$packet->legacyHasItemStackIds = true;

		$decoded = new InventoryTransactionPacket();
		$decoded->decode($reader = new ByteBufferReader(self::encode($packet, ProtocolInfo::PROTOCOL_1_16_200)), ProtocolInfo::PROTOCOL_1_16_200);
		self::assertSame(0, $reader->getUnreadLength());
		self::assertTrue($decoded->legacyHasItemStackIds);
		self::assertSame(4, $decoded->trData->getActions()[0]->legacyNewItemStackId);
	}

	public function testMovementSettingsLayouts() : void{
		$settings = new PlayerMovementSettings(ServerAuthMovementMode::SERVER_AUTHORITATIVE_V2, 0, false);
		foreach([
			ProtocolInfo::PROTOCOL_1_16_20 => "\x01",
			ProtocolInfo::PROTOCOL_1_16_200 => "\x02",
			ProtocolInfo::PROTOCOL_1_16_210 => "\x02\x00\x00",
		] as $protocolId => $expected){
			$out = new ByteBufferWriter();
			$settings->write($out, $protocolId);
			self::assertSame($expected, $out->getData(), "protocol $protocolId");
		}
	}

	public function testSkinFieldsBefore1_16_210() : void{
		$empty = new SkinImage(0, 0, '');
		$skin = new SkinData('id', 'playfab', 'patch', $empty, [new SkinAnimation($empty, 1, 2.0, 1)], $empty);
		$encode = static function(int $protocolId) use ($skin) : string{
			$out = new ByteBufferWriter();
			CommonTypes::putSkin($out, $protocolId, $skin);
			return $out->getData();
		};
		self::assertStringStartsWith("\x02id\x05patch", $encode(ProtocolInfo::PROTOCOL_1_16_200));
		self::assertStringStartsWith("\x02id\x07playfab\x05patch", $encode(ProtocolInfo::PROTOCOL_1_16_210));
		//the animation expression type (4 bytes) appeared in 1.16.100
		self::assertSame(strlen($encode(ProtocolInfo::PROTOCOL_1_16_20)) + 4, strlen($encode(ProtocolInfo::PROTOCOL_1_16_100)));

		foreach([ProtocolInfo::PROTOCOL_1_16_20, ProtocolInfo::PROTOCOL_1_16_200, ProtocolInfo::PROTOCOL_1_16_210] as $protocolId){
			$decoded = CommonTypes::getSkin($reader = new ByteBufferReader($encode($protocolId)), $protocolId);
			self::assertSame(0, $reader->getUnreadLength());
			self::assertSame('patch', $decoded->getResourcePatch());
		}
	}

	public function testStackRequestActionIds() : void{
		//CraftRecipe (modern 12) is wire 9 before 1.16.210 and 10 afterwards; the deprecated results action moved twice
		foreach([ProtocolInfo::PROTOCOL_1_16_0 => [9, 13], ProtocolInfo::PROTOCOL_1_16_200 => [9, 14], ProtocolInfo::PROTOCOL_1_16_210 => [10, 15]] as $protocolId => [$craftId, $resultsId]){
			$wire = "\x02\x02" . chr($craftId) . "\x05" . chr($resultsId) . "\x00\x00" . ($protocolId >= ProtocolInfo::PROTOCOL_1_16_200 ? "\x00" : "");
			$request = ItemStackRequest::read($reader = new ByteBufferReader($wire), $protocolId);
			self::assertSame(0, $reader->getUnreadLength(), "protocol $protocolId");
			self::assertInstanceOf(CraftRecipeStackRequestAction::class, $request->getActions()[0]);
			self::assertInstanceOf(DeprecatedCraftingResultsStackRequestAction::class, $request->getActions()[1]);
			$out = new ByteBufferWriter();
			$request->write($out, $protocolId);
			self::assertSame($wire, $out->getData());
		}
		$creative = ItemStackRequest::read(new ByteBufferReader("\x02\x01\x0b\x06"), ProtocolInfo::PROTOCOL_1_16_20);
		self::assertInstanceOf(CreativeCreateStackRequestAction::class, $creative->getActions()[0]);
	}

	public function testStackResponseUsedSuccessFlagBefore1_16_100() : void{
		$out = new ByteBufferWriter();
		(new ItemStackResponse(ItemStackResponse::RESULT_OK, 1, []))->write($out, ProtocolInfo::PROTOCOL_1_16_20);
		self::assertSame("\x01\x02\x00", $out->getData());
		$decoded = ItemStackResponse::read(new ByteBufferReader("\x00\x02"), ProtocolInfo::PROTOCOL_1_16_20);
		self::assertSame(ItemStackResponse::RESULT_ERROR, $decoded->getResult());
	}

	public function testMetadataKeysBefore1_16_210() : void{
		$metadata = [
			10 => new IntMetadataProperty(1),
			60 => new IntMetadataProperty(2), //did not exist yet
			61 => new IntMetadataProperty(3),
			110 => new StringMetadataProperty("s"),
			111 => new IntMetadataProperty(4), //did not exist yet
		];
		$out = new ByteBufferWriter();
		CommonTypes::putEntityMetadata($out, ProtocolInfo::PROTOCOL_1_16_200, $metadata);
		$decoded = CommonTypes::getEntityMetadata(new ByteBufferReader($out->getData()), ProtocolInfo::PROTOCOL_1_16_200);
		self::assertSame([10, 61, 110], array_keys(array_filter($decoded, fn($k) => $k !== 0 && $k !== 92, ARRAY_FILTER_USE_KEY)));
	}

	public function testResourcePackStackExperimentalFlag() : void{
		$packet = ResourcePackStackPacket::create([], [], false, "*", new Experiments([], false), false);
		$old = self::encode($packet, ProtocolInfo::PROTOCOL_1_16_20);
		$new = self::encode($packet, ProtocolInfo::PROTOCOL_1_16_100);
		self::assertSame(strlen($old) + 4, strlen($new)); //bool flag replaced by an empty experiment list and a bool
		$decoded = new ResourcePackStackPacket();
		$decoded->decode($reader = new ByteBufferReader($old), ProtocolInfo::PROTOCOL_1_16_20);
		self::assertSame(0, $reader->getUnreadLength());
	}

	public function testStartGameLayouts() : void{
		$palette = (new NetworkNbtSerializer())->write(new TreeRoot(new ListTag([
			CompoundTag::create()->setTag("block", CompoundTag::create()->setString("name", "minecraft:air"))->setShort("id", 0)
		])));
		$levelSettings = new LevelSettings();
		$levelSettings->seed = 1;
		$levelSettings->spawnSettings = new SpawnSettings(SpawnSettings::BIOME_TYPE_DEFAULT, "", DimensionIds::OVERWORLD);
		$levelSettings->worldGamemode = 0;
		$levelSettings->difficulty = 1;
		$levelSettings->spawnPosition = new BlockPosition(0, 64, 0);
		$levelSettings->rainLevel = 0;
		$levelSettings->lightningLevel = 0;
		$levelSettings->commandsEnabled = true;
		$levelSettings->gameRules = ["naturalregeneration" => new BoolGameRule(false, false)];
		$levelSettings->experiments = new Experiments([], false);
		$packet = StartGamePacket::create(1, 1, 0, new Vector3(0, 64, 0), 0, 0, new CacheableNbt(new CompoundTag()), $levelSettings, "", "world", "", false,
			new PlayerMovementSettings(ServerAuthMovementMode::SERVER_AUTHORITATIVE_V2, 0, false), 0, 0, "", true, "EraseMC", Uuid::fromString(Uuid::NIL),
			false, false, false, new NetworkPermissions(false), false, null, new ServerTelemetryData("", "", "", ""), [], 0,
			[new ItemTypeEntry("minecraft:stone", 1, false, -1, new CacheableNbt(new CompoundTag()))]);
		$packet->legacyBlockPaletteNbt = $palette;

		foreach([ProtocolInfo::PROTOCOL_1_16_0, ProtocolInfo::PROTOCOL_1_16_100, ProtocolInfo::PROTOCOL_1_16_220, ProtocolInfo::PROTOCOL_1_17_0] as $protocolId){
			$wire = self::encode($packet, $protocolId);
			self::assertSame($protocolId < ProtocolInfo::PROTOCOL_1_16_100, str_contains($wire, $palette), "protocol $protocolId");
			self::assertSame($protocolId >= ProtocolInfo::PROTOCOL_1_17_0, str_ends_with($wire, "\x07EraseMC"), "protocol $protocolId");
			$decoded = new StartGamePacket();
			$decoded->decode($reader = new ByteBufferReader($wire), $protocolId);
			self::assertSame(0, $reader->getUnreadLength(), "protocol $protocolId");
			self::assertSame("world", $decoded->worldName);
			self::assertSame("minecraft:stone", $decoded->itemTable[0]->getStringId());
			self::assertArrayHasKey("naturalregeneration", $decoded->levelSettings->gameRules);
		}
	}
}
