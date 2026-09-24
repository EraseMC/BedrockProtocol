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

use PHPUnit\Framework\TestCase;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientData;
use pocketmine\network\mcpe\protocol\types\login\clientdata\ClientDataToSkinDataHelper;
use pocketmine\network\mcpe\protocol\types\skin\SkinArmSizeType;
use function base64_encode;
use function str_repeat;

class ClientDataToSkinDataHelperTest extends TestCase{

	private static function clientData(string $armSize, string $skinColor, string $geometry) : ClientData{
		$data = new ClientData();
		$data->AnimatedImageData = [];
		$data->ArmSize = $armSize;
		$data->CapeData = "";
		$data->CapeId = "";
		$data->CapeImageHeight = 0;
		$data->CapeImageWidth = 0;
		$data->CapeOnClassicSkin = false;
		$data->PersonaPieces = [];
		$data->PersonaSkin = false;
		$data->PieceTintColors = [];
		$data->PlayFabId = "";
		$data->SkinAnimationData = "";
		$data->SkinColor = $skinColor;
		$data->SkinData = base64_encode(str_repeat("\x00", 64 * 32 * 4));
		$data->SkinGeometryData = "";
		$data->SkinId = "Standard_Custom";
		$data->SkinImageHeight = 32;
		$data->SkinImageWidth = 64;
		$data->SkinResourcePatch = base64_encode('{"geometry":{"default":"' . $geometry . '"}}');
		$data->OverrideSkin = true;

		return $data;
	}

	public function testExplicitArmSizeIsKept() : void{
		$skin = ClientDataToSkinDataHelper::fromClientData(self::clientData("slim", "#0", "geometry.humanoid.custom"));

		self::assertSame(SkinArmSizeType::SLIM, $skin->getArmSize());
	}

	public function testLegacyEmptyArmSizeFollowsSlimGeometry() : void{
		$skin = ClientDataToSkinDataHelper::fromClientData(self::clientData("", "#0", "geometry.humanoid.customSlim"));

		self::assertSame(SkinArmSizeType::SLIM, $skin->getArmSize());
	}

	public function testLegacyEmptyArmSizeDefaultsToWide() : void{
		$skin = ClientDataToSkinDataHelper::fromClientData(self::clientData("", "", "geometry.humanoid.custom"));

		self::assertSame(SkinArmSizeType::WIDE, $skin->getArmSize());
		self::assertSame(0, $skin->getSkinColor()->toARGB());
	}
}
