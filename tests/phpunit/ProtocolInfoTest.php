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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProtocolInfoTest extends TestCase{

	public function testMinecraftVersionNetwork() : void{
		self::assertMatchesRegularExpression(
			'/^(?:\d+\.)?(?:\d+\.)?(?:\d+\.)?\d+$/',
			ProtocolInfo::MINECRAFT_VERSION_NETWORK,
			"Network version should only contain 0-9 and \".\", and no more than 4 groups of digits"
		);
	}

	/**
	 * Stable 1.19 release profiles only. Preview protocol IDs are deliberately
	 * omitted because they require their own compatibility and client test matrix.
	 *
	 * @phpstan-return \Generator<string, array{int, int}>
	 */
	public static function stable1_19ProtocolProvider() : \Generator{
		yield '1.19.0' => [ProtocolInfo::PROTOCOL_1_19_0, 527];
		yield '1.19.10' => [ProtocolInfo::PROTOCOL_1_19_10, 534];
		yield '1.19.20' => [ProtocolInfo::PROTOCOL_1_19_20, 544];
		yield '1.19.21' => [ProtocolInfo::PROTOCOL_1_19_21, 545];
		yield '1.19.30' => [ProtocolInfo::PROTOCOL_1_19_30, 554];
		yield '1.19.40' => [ProtocolInfo::PROTOCOL_1_19_40, 557];
		yield '1.19.50' => [ProtocolInfo::PROTOCOL_1_19_50, 560];
		yield '1.19.60' => [ProtocolInfo::PROTOCOL_1_19_60, 567];
		yield '1.19.63' => [ProtocolInfo::PROTOCOL_1_19_63, 568];
		yield '1.19.70' => [ProtocolInfo::PROTOCOL_1_19_70, 575];
		yield '1.19.80' => [ProtocolInfo::PROTOCOL_1_19_80, 582];
	}

	#[DataProvider('stable1_19ProtocolProvider')]
	public function testStable1_19ProtocolConstants(int $actual, int $expected) : void{
		self::assertSame($expected, $actual);
	}

	public function testProfileConfigurationMatchesGeneratedProtocolInfo() : void{
		/** @var array<string, array{protocolId: int, accepted: bool}> $profiles */
		$profiles = require dirname(__DIR__, 2) . '/tools/protocol-profiles.php';
		$acceptedProtocolIds = [];
		foreach($profiles as $name => $profile){
			self::assertSame($profile['protocolId'], constant(ProtocolInfo::class . '::' . $name));
			if($profile['accepted']){
				$acceptedProtocolIds[] = $profile['protocolId'];
			}
		}

		sort($acceptedProtocolIds);
		$generatedAcceptedProtocolIds = ProtocolInfo::ACCEPTED_PROTOCOL;
		sort($generatedAcceptedProtocolIds);
		self::assertSame($acceptedProtocolIds, $generatedAcceptedProtocolIds);
	}
}
