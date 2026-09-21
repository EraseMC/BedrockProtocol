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

/*
 * Named released protocol profiles used by ProtocolInfo generation.
 *
 * The second value controls whether a profile is advertised in
 * ProtocolInfo::ACCEPTED_PROTOCOL. A profile must remain false until its
 * transport, data translation, packet codecs, and client compatibility matrix
 * have passed their release gates. Preview protocol IDs are deliberately not
 * listed here.
 *
 * @return array<string, array{protocolId: int, accepted: bool}>
 */
return [
	'PROTOCOL_1_18_0' => ['protocolId' => 475, 'accepted' => true],
	'PROTOCOL_1_18_10' => ['protocolId' => 486, 'accepted' => true],
	'PROTOCOL_1_18_30' => ['protocolId' => 503, 'accepted' => true],
	'PROTOCOL_1_19_0' => ['protocolId' => 527, 'accepted' => false],
	'PROTOCOL_1_19_10' => ['protocolId' => 534, 'accepted' => false],
	'PROTOCOL_1_19_20' => ['protocolId' => 544, 'accepted' => false],
	'PROTOCOL_1_19_21' => ['protocolId' => 545, 'accepted' => false],
	'PROTOCOL_1_19_30' => ['protocolId' => 554, 'accepted' => false],
	'PROTOCOL_1_19_40' => ['protocolId' => 557, 'accepted' => false],
	'PROTOCOL_1_19_50' => ['protocolId' => 560, 'accepted' => false],
	'PROTOCOL_1_19_60' => ['protocolId' => 567, 'accepted' => false],
	'PROTOCOL_1_19_63' => ['protocolId' => 568, 'accepted' => false],
	'PROTOCOL_1_19_70' => ['protocolId' => 575, 'accepted' => false],
	'PROTOCOL_1_19_80' => ['protocolId' => 582, 'accepted' => false],
	'PROTOCOL_1_20_0' => ['protocolId' => 589, 'accepted' => true],
	'PROTOCOL_1_20_10' => ['protocolId' => 594, 'accepted' => true],
	'PROTOCOL_1_20_30' => ['protocolId' => 618, 'accepted' => true],
	'PROTOCOL_1_20_40' => ['protocolId' => 622, 'accepted' => true],
	'PROTOCOL_1_20_50' => ['protocolId' => 630, 'accepted' => true],
	'PROTOCOL_1_20_60' => ['protocolId' => 649, 'accepted' => true],
	'PROTOCOL_1_20_70' => ['protocolId' => 662, 'accepted' => true],
	'PROTOCOL_1_20_80' => ['protocolId' => 671, 'accepted' => true],
	'PROTOCOL_1_21_0' => ['protocolId' => 685, 'accepted' => true],
	'PROTOCOL_1_21_2' => ['protocolId' => 686, 'accepted' => true],
	'PROTOCOL_1_21_20' => ['protocolId' => 712, 'accepted' => true],
	'PROTOCOL_1_21_30' => ['protocolId' => 729, 'accepted' => true],
	'PROTOCOL_1_21_40' => ['protocolId' => 748, 'accepted' => true],
	'PROTOCOL_1_21_50' => ['protocolId' => 766, 'accepted' => true],
	'PROTOCOL_1_21_60' => ['protocolId' => 776, 'accepted' => true],
	'PROTOCOL_1_21_70' => ['protocolId' => 786, 'accepted' => true],
	'PROTOCOL_1_21_80' => ['protocolId' => 800, 'accepted' => true],
	'PROTOCOL_1_21_90' => ['protocolId' => 818, 'accepted' => true],
	'PROTOCOL_1_21_93' => ['protocolId' => 819, 'accepted' => true],
	'PROTOCOL_1_21_100' => ['protocolId' => 827, 'accepted' => true],
	'PROTOCOL_1_21_111' => ['protocolId' => 844, 'accepted' => true],
	'PROTOCOL_1_21_120' => ['protocolId' => 859, 'accepted' => true],
	'PROTOCOL_1_21_124' => ['protocolId' => 860, 'accepted' => true],
	'PROTOCOL_1_21_130' => ['protocolId' => 898, 'accepted' => true],
	'PROTOCOL_1_26_0' => ['protocolId' => 924, 'accepted' => true],
	'PROTOCOL_1_26_10' => ['protocolId' => 944, 'accepted' => true],
	'PROTOCOL_1_26_20' => ['protocolId' => 975, 'accepted' => true],
	'PROTOCOL_1_26_30' => ['protocolId' => 1001, 'accepted' => true],
	'PROTOCOL_1_26_40' => ['protocolId' => 2167, 'accepted' => true],
	'PROTOCOL_1_26_44' => ['protocolId' => 2168, 'accepted' => true],
	'PROTOCOL_1_26_45' => ['protocolId' => 2169, 'accepted' => true],
	'PROTOCOL_1_26_50' => ['protocolId' => 2193, 'accepted' => true],
];
