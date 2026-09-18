# EraseMC BedrockProtocol
[![CI](https://github.com/EraseMC/BedrockProtocol/actions/workflows/ci.yml/badge.svg)](https://github.com/EraseMC/BedrockProtocol/actions/workflows/ci.yml)

An EraseMC-maintained implementation of the Minecraft: Bedrock Edition protocol in PHP.

This repository is part of the EraseMC Core stack and is published as `erasemc/bedrock-protocol`.

This library implements all of the packets in the Minecraft: Bedrock Edition protocol, as well as a few extra things needed to support them.
However, at the time of writing, it does _not_ include the following:
- Anything related to JWT handling/verification
- Anything related to encryption
- Anything related to compression

## Decoding packets
Assuming you've decrypted and decompressed a Minecraft packet successfully, you're next going to want to decode it.
With this library, that's currently done using `PacketBatch`, like so:

```php
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\PacketPool;
use pmmp\encoding\ByteBufferReader;

foreach(PacketBatch::decodePackets(new ByteBufferReader($payload), PacketPool::getInstance()) as $packetObject){
    var_dump($packetObject); //tada
}
```

## Encoding packets
This is easy:

```php
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pmmp\encoding\ByteBufferWriter;

/** @var Packet[] $packets */
$stream = new ByteBufferWriter();
PacketBatch::encodePackets($stream, $packets);
$batchPayload = $stream->getData();
```

## Footnotes
This library is a little rough around the edges, since it originated as a component of PocketMine-MP. It is maintained separately by EraseMC so it can evolve with the EraseMC Core compatibility roadmap.
This means that API changes might be in order, and your feedback would be nice to drive them.
If you want to improve BedrockProtocol, please open issues with suggestions, or better, make pull requests.
