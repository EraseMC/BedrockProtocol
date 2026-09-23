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

namespace pocketmine\network\mcpe\protocol\types\inventory;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\DataDecodeException;
use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;

abstract class TransactionData{

	/**
	 * @var NetworkInventoryAction[]
	 * @phpstan-var list<NetworkInventoryAction>
	 */
	protected array $actions = [];

	/**
	 * @return NetworkInventoryAction[]
	 * @phpstan-return list<NetworkInventoryAction>
	 */
	final public function getActions() : array{
		return $this->actions;
	}

	abstract public function getTypeId() : int;

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	final public function decodeTransaction(ByteBufferReader $in, int $protocolId, bool $legacyHasItemStackIds = false) : void{
		$this->actions = CommonTypes::readList($in, static fn($in) => (new NetworkInventoryAction())->readTransaction($in, $protocolId, $legacyHasItemStackIds));
		$this->decodeData($in, $protocolId);
	}

	/**
	 * @throws DataDecodeException
	 */
	final public function decodeAuthInput(ByteBufferReader $in, int $protocolId) : void{
		$this->actions = CommonTypes::readList($in, static fn($in) => (new NetworkInventoryAction())->readAuthInput($in, $protocolId));
		if($protocolId <= ProtocolInfo::PROTOCOL_1_17_40){
			// In 1.17, ItemInteractionData contains the complete UseItemTransactionData.
			// The action list alone is not the end of the payload.
			$this->decodeData($in, $protocolId);
		}
	}

	/**
	 * @throws DataDecodeException
	 * @throws PacketDecodeException
	 */
	abstract protected function decodeData(ByteBufferReader $in, int $protocolId) : void;

	final public function encodeTransaction(ByteBufferWriter $out, int $protocolId, bool $legacyHasItemStackIds = false) : void{
		CommonTypes::writeList($out, $this->actions, static fn($out, $a) => $a->writeTransaction($out, $protocolId, $legacyHasItemStackIds));
		$this->encodeData($out, $protocolId);
	}

	final public function encodeAuthInput(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::writeList($out, $this->actions, static fn($out, $a) => $a->writeAuthInput($out, $protocolId));
		if($protocolId <= ProtocolInfo::PROTOCOL_1_17_40){
			$this->encodeData($out, $protocolId);
		}
	}

	abstract protected function encodeData(ByteBufferWriter $out, int $protocolId) : void;
}
