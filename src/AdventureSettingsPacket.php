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

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\PlayerPermissions;

/**
 * Legacy abilities packet used by the 1.19.0 profile only.
 *
 * Newer 1.19 clients embed UpdateAbilitiesPacket in AddPlayerPacket instead.
 */
class AdventureSettingsPacket extends DataPacket implements ClientboundPacket, ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::ADVENTURE_SETTINGS_PACKET;

	/** Flags stored in the second bit field. */
	public const BITFLAG_SECOND_SET = 1 << 16;
	private const SECOND_FLAGS_MASK = self::BITFLAG_SECOND_SET - 1;

	public const WORLD_IMMUTABLE = 0x01;
	public const NO_PVP = 0x02;
	public const AUTO_JUMP = 0x20;
	public const ALLOW_FLIGHT = 0x40;
	public const NO_CLIP = 0x80;
	public const WORLD_BUILDER = 0x100;
	public const FLYING = 0x200;
	public const MUTED = 0x400;

	public const MINE = 0x01 | self::BITFLAG_SECOND_SET;
	public const DOORS_AND_SWITCHES = 0x02 | self::BITFLAG_SECOND_SET;
	public const OPEN_CONTAINERS = 0x04 | self::BITFLAG_SECOND_SET;
	public const ATTACK_PLAYERS = 0x08 | self::BITFLAG_SECOND_SET;
	public const ATTACK_MOBS = 0x10 | self::BITFLAG_SECOND_SET;
	public const OPERATOR = 0x20 | self::BITFLAG_SECOND_SET;
	public const TELEPORT = 0x80 | self::BITFLAG_SECOND_SET;
	public const BUILD = 0x100 | self::BITFLAG_SECOND_SET;
	public const DEFAULT = 0x200 | self::BITFLAG_SECOND_SET;

	public int $flags = 0;
	public int $commandPermission = CommandPermissions::NORMAL;
	public int $flags2 = -1;
	public int $playerPermission = PlayerPermissions::MEMBER;
	public int $customFlags = 0;
	/** Little-endian signed long, not the normal actor-unique-ID varlong. */
	public int $targetActorUniqueId;

	/**
	 * @generate-create-func
	 */
	public static function create(int $flags, int $commandPermission, int $flags2, int $playerPermission, int $customFlags, int $targetActorUniqueId) : self{
		$result = new self;
		$result->flags = $flags;
		$result->commandPermission = $commandPermission;
		$result->flags2 = $flags2;
		$result->playerPermission = $playerPermission;
		$result->customFlags = $customFlags;
		$result->targetActorUniqueId = $targetActorUniqueId;
		return $result;
	}

	public static function readLegacyPayload(ByteBufferReader $in) : self{
		$result = new self;
		$result->flags = VarInt::readUnsignedInt($in);
		$result->commandPermission = VarInt::readUnsignedInt($in);
		$result->flags2 = VarInt::readUnsignedInt($in);
		$result->playerPermission = VarInt::readUnsignedInt($in);
		$result->customFlags = VarInt::readUnsignedInt($in);
		$result->targetActorUniqueId = LE::readSignedLong($in);
		return $result;
	}

	public function writeLegacyPayload(ByteBufferWriter $out) : void{
		VarInt::writeUnsignedInt($out, $this->flags);
		VarInt::writeUnsignedInt($out, $this->commandPermission);
		VarInt::writeUnsignedInt($out, $this->flags2);
		VarInt::writeUnsignedInt($out, $this->playerPermission);
		VarInt::writeUnsignedInt($out, $this->customFlags);
		LE::writeSignedLong($out, $this->targetActorUniqueId);
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$decoded = self::readLegacyPayload($in);
		$this->flags = $decoded->flags;
		$this->commandPermission = $decoded->commandPermission;
		$this->flags2 = $decoded->flags2;
		$this->playerPermission = $decoded->playerPermission;
		$this->customFlags = $decoded->customFlags;
		$this->targetActorUniqueId = $decoded->targetActorUniqueId;
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		$this->writeLegacyPayload($out);
	}

	public function getFlag(int $flag) : bool{
		if(($flag & self::BITFLAG_SECOND_SET) !== 0){
			return ($this->flags2 & ($flag & self::SECOND_FLAGS_MASK)) !== 0;
		}
		return ($this->flags & $flag) !== 0;
	}

	public function setFlag(int $flag, bool $value) : void{
		if(($flag & self::BITFLAG_SECOND_SET) !== 0){
			$flagSet = &$this->flags2;
			$flag &= self::SECOND_FLAGS_MASK;
		}else{
			$flagSet = &$this->flags;
		}
		if($value){
			$flagSet |= $flag;
		}else{
			$flagSet &= ~$flag;
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleAdventureSettings($this);
	}
}
