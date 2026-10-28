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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pmmp\encoding\Byte;
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

final class ItemStackRequest{
	/**
	 * @param ItemStackRequestAction[] $actions
	 * @param string[]                 $filterStrings
	 * @phpstan-param list<ItemStackRequestAction> $actions
	 * @phpstan-param list<string> $filterStrings
	 */
	public function __construct(
		private int $requestId,
		private array $actions,
		private array $filterStrings,
		private int $filterStringCause
	){}

	public function getRequestId() : int{ return $this->requestId; }

	/**
	 * @return ItemStackRequestAction[]
	 * @phpstan-return list<ItemStackRequestAction>
	 */
	public function getActions() : array{ return $this->actions; }

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getFilterStrings() : array{ return $this->filterStrings; }

	public function getFilterStringCause() : int{ return $this->filterStringCause; }

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	private static function readAction(ByteBufferReader $in, int $protocolId, int $typeId) : ItemStackRequestAction{
		return match($typeId){
			TakeStackRequestAction::ID => TakeStackRequestAction::read($in, $protocolId),
			PlaceStackRequestAction::ID => PlaceStackRequestAction::read($in, $protocolId),
			SwapStackRequestAction::ID => SwapStackRequestAction::read($in, $protocolId),
			DropStackRequestAction::ID => DropStackRequestAction::read($in, $protocolId),
			DestroyStackRequestAction::ID => DestroyStackRequestAction::read($in, $protocolId),
			CraftingConsumeInputStackRequestAction::ID => CraftingConsumeInputStackRequestAction::read($in, $protocolId),
			CraftingCreateSpecificResultStackRequestAction::ID => CraftingCreateSpecificResultStackRequestAction::read($in, $protocolId),
			PlaceIntoBundleStackRequestAction::ID => PlaceIntoBundleStackRequestAction::read($in, $protocolId),
			TakeFromBundleStackRequestAction::ID => TakeFromBundleStackRequestAction::read($in, $protocolId),
			LabTableCombineStackRequestAction::ID => LabTableCombineStackRequestAction::read($in, $protocolId),
			BeaconPaymentStackRequestAction::ID => BeaconPaymentStackRequestAction::read($in, $protocolId),
			MineBlockStackRequestAction::ID => MineBlockStackRequestAction::read($in, $protocolId),
			CraftRecipeStackRequestAction::ID => CraftRecipeStackRequestAction::read($in, $protocolId),
			CraftRecipeAutoStackRequestAction::ID => CraftRecipeAutoStackRequestAction::read($in, $protocolId),
			CreativeCreateStackRequestAction::ID => CreativeCreateStackRequestAction::read($in, $protocolId),
			CraftRecipeOptionalStackRequestAction::ID => CraftRecipeOptionalStackRequestAction::read($in, $protocolId),
			GrindstoneStackRequestAction::ID => GrindstoneStackRequestAction::read($in, $protocolId),
			LoomStackRequestAction::ID => LoomStackRequestAction::read($in, $protocolId),
			CraftReservedStackRequestAction::ID => CraftReservedStackRequestAction::read($in),
			DeprecatedCraftingNonImplementedStackRequestAction::ID => DeprecatedCraftingNonImplementedStackRequestAction::read($in, $protocolId),
			DeprecatedCraftingResultsStackRequestAction::ID => DeprecatedCraftingResultsStackRequestAction::read($in, $protocolId),
			default => throw new PacketDecodeException("Unhandled item stack request action type $typeId"),
		};
	}

	/**
	 * Converts a single type ID of an older protocol to the 1.18.10 - 1.26.30 numbering used by
	 * {@link ItemStackRequestActionType::fromLegacyId()}.
	 */
	private static function normaliseLegacyTypeId(int $protocolId, int $typeId) : int{
		//1.16.210 inserted MINE_BLOCK at 9 and 1.16.200 inserted CRAFTING_RECIPE_OPTIONAL at 13
		if($protocolId < ProtocolInfo::PROTOCOL_1_16_210 && $typeId >= 9){
			++$typeId;
		}
		if($protocolId < ProtocolInfo::PROTOCOL_1_16_200 && $typeId >= 13){
			++$typeId;
		}
		//1.18.0 inserted grindstone and loom at 14 and 15, before the two deprecated crafting actions
		if($protocolId < ProtocolInfo::PROTOCOL_1_18_0 && $typeId >= 14){
			$typeId += 2;
		}
		//1.18.10 inserted the bundle actions at 7 and 8
		if($protocolId < ProtocolInfo::PROTOCOL_1_18_10 && $typeId >= 7){
			$typeId += 2;
		}
		return $typeId;
	}

	/**
	 * Inverse of {@link ItemStackRequest::normaliseLegacyTypeId()}.
	 *
	 * @throws \InvalidArgumentException if the action doesn't exist in the given protocol
	 */
	private static function denormaliseLegacyTypeId(int $protocolId, int $typeId) : int{
		if($protocolId < ProtocolInfo::PROTOCOL_1_18_10){
			if($typeId === 7 || $typeId === 8){
				throw new \InvalidArgumentException("Item stack request action $typeId does not exist in protocol $protocolId");
			}
			if($typeId > 8){
				$typeId -= 2;
			}
		}
		if($protocolId < ProtocolInfo::PROTOCOL_1_18_0){
			if($typeId === 14 || $typeId === 15){
				throw new \InvalidArgumentException("Item stack request action $typeId does not exist in protocol $protocolId");
			}
			if($typeId > 15){
				$typeId -= 2;
			}
		}
		if($protocolId < ProtocolInfo::PROTOCOL_1_16_200){
			if($typeId === 13){
				throw new \InvalidArgumentException("Item stack request action $typeId does not exist in protocol $protocolId");
			}
			if($typeId > 13){
				--$typeId;
			}
		}
		if($protocolId < ProtocolInfo::PROTOCOL_1_16_210){
			if($typeId === 9){
				throw new \InvalidArgumentException("Item stack request action $typeId does not exist in protocol $protocolId");
			}
			if($typeId > 9){
				--$typeId;
			}
		}
		return $typeId;
	}

	public static function read(ByteBufferReader $in, int $protocolId) : self{
		$requestId = CommonTypes::readItemStackRequestId($in);
		$actions = CommonTypes::readList($in, static function(ByteBufferReader $in) use ($protocolId) : ItemStackRequestAction{
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_60){
				$typeId = VarInt::readUnsignedInt($in);
				$innerTypeId = Byte::readUnsigned($in);
				$expectedInnerType = ItemStackRequestActionType::INNER_TYPES[$typeId] ?? "unknown";
				if($expectedInnerType !== $innerTypeId){
					throw new PacketDecodeException("ItemStackRequestAction type mismatch: outer type $typeId, expected inner type $expectedInnerType, actual inner type $innerTypeId");
				}
			}elseif($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				//1.26.40 - 1.26.50: pre-1.26.60 outer types, pre-1.26.40 inner IDs
				$outerTypeId = VarInt::readUnsignedInt($in);
				$innerTypeId = Byte::readUnsigned($in);
				$typeId = ItemStackRequestActionType::fromPre1_26_60OuterId($outerTypeId) ?? throw new PacketDecodeException("Unhandled item stack request action type $outerTypeId");
				$expectedInnerType = ItemStackRequestActionType::toLegacyId($typeId) ?? "unknown";
				if($expectedInnerType !== $innerTypeId){
					throw new PacketDecodeException("ItemStackRequestAction type mismatch: outer type $outerTypeId, expected inner type $expectedInnerType, actual inner type $innerTypeId");
				}
			}else{
				$legacyTypeId = self::normaliseLegacyTypeId($protocolId, Byte::readUnsigned($in));
				$typeId = ItemStackRequestActionType::fromLegacyId($legacyTypeId) ?? throw new PacketDecodeException("Unhandled item stack request action type $legacyTypeId");
			}
			return self::readAction($in, $protocolId, $typeId);
		});
		$filterStrings = $protocolId >= ProtocolInfo::PROTOCOL_1_16_200 ? CommonTypes::readList($in, CommonTypes::getString(...)) : [];
		$filterStringCause = $protocolId >= ProtocolInfo::PROTOCOL_1_19_30 ? LE::readSignedInt($in) : 0;
		return new self($requestId, $actions, $filterStrings, $filterStringCause);
	}

	public function write(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::writeItemStackRequestId($out, $this->requestId);
		CommonTypes::writeList($out, $this->actions, static function(ByteBufferWriter $out, ItemStackRequestAction $action) use ($protocolId) : void{
			$typeId = $action->getTypeId();
			if($protocolId >= ProtocolInfo::PROTOCOL_1_26_60){
				$innerTypeId = ItemStackRequestActionType::INNER_TYPES[$typeId] ?? throw new \InvalidArgumentException("Item stack request action type $typeId is not supported by protocol $protocolId");
				VarInt::writeUnsignedInt($out, $typeId);
				Byte::writeUnsigned($out, $innerTypeId);
			}elseif($protocolId >= ProtocolInfo::PROTOCOL_1_26_40){
				$outerTypeId = ItemStackRequestActionType::toPre1_26_60OuterId($typeId) ?? throw new \InvalidArgumentException("Item stack request action type $typeId is not supported by protocol $protocolId");
				$innerTypeId = ItemStackRequestActionType::toLegacyId($typeId) ?? throw new \InvalidArgumentException("Item stack request action type $typeId is not supported by protocol $protocolId");
				VarInt::writeUnsignedInt($out, $outerTypeId);
				Byte::writeUnsigned($out, $innerTypeId);
			}else{
				Byte::writeUnsigned($out, self::denormaliseLegacyTypeId($protocolId, ItemStackRequestActionType::toLegacyId($typeId) ?? throw new \InvalidArgumentException("Item stack request action type $typeId is not supported by protocol $protocolId")));
			}
			$action->write($out, $protocolId);
		});
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_200){
			CommonTypes::writeList($out, $this->filterStrings, CommonTypes::putString(...));
		}
		if($protocolId >= ProtocolInfo::PROTOCOL_1_19_30){
			LE::writeSignedInt($out, $this->filterStringCause);
		}
	}
}
