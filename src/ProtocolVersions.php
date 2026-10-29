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

use function is_int;
use function preg_match;
use function str_replace;
use function version_compare;

/**
 * Game versions named by the protocol constants, so that a version stamp newer than the receiving client can be
 * kept within what that client knows.
 */
final class ProtocolVersions{
	/** @var array<int, string>|null */
	private static ?array $names = null;

	private function __construct(){
		//NOOP
	}

	/**
	 * Returns the oldest game version speaking the given protocol, or null if no constant names it.
	 */
	public static function nameOf(int $protocolId) : ?string{
		if(self::$names === null){
			self::$names = [];
			foreach((new \ReflectionClass(ProtocolInfo::class))->getConstants() as $constant => $value){
				if(!is_int($value) || preg_match('/^PROTOCOL_(\d+(?:_\d+)*)$/', $constant, $matches) !== 1){
					continue;
				}
				$version = str_replace('_', '.', $matches[1]);
				if(!isset(self::$names[$value]) || version_compare($version, self::$names[$value], '<')){
					self::$names[$value] = $version;
				}
			}
		}
		return self::$names[$protocolId] ?? null;
	}

	/**
	 * Returns $version when the client speaking $protocolId knows it, otherwise $fallback.
	 */
	public static function clamp(string $version, int $protocolId, string $fallback) : string{
		$name = self::nameOf($protocolId);
		if($name === null || $version === '*' || version_compare($version, $name, '<=')){
			return $version;
		}
		return $fallback;
	}

	/**
	 * Returns $version limited to the game version of the client speaking $protocolId.
	 */
	public static function limit(string $version, int $protocolId) : string{
		return self::clamp($version, $protocolId, self::nameOf($protocolId) ?? $version);
	}
}
