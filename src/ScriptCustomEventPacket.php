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
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

/**
 * Legacy scripting API event, used by 1.16 clients.
 */
class ScriptCustomEventPacket extends DataPacket implements ClientboundPacket, ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::SCRIPT_CUSTOM_EVENT_PACKET;

	public string $eventName;
	public string $eventData;

	/**
	 * @generate-create-func
	 */
	public static function create(string $eventName, string $eventData) : self{
		$result = new self;
		$result->eventName = $eventName;
		$result->eventData = $eventData;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->eventName = CommonTypes::getString($in);
		$this->eventData = CommonTypes::getString($in);
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putString($out, $this->eventName);
		CommonTypes::putString($out, $this->eventData);
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleScriptCustomEvent($this);
	}
}
