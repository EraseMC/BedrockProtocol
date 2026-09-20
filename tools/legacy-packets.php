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
 * Packets absent from the newest BedrockData protocol_info.json but required
 * by one or more enabled legacy profiles. Keep each entry until its final
 * supported profile is retired.
 *
 * @return array<class-string, int>
 */
return [
	'AdventureSettingsPacket' => 0x37,
];
