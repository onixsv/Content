<?php
declare(strict_types=1);

namespace Content;

use Content\event\ContentClearEvent;
use ojy\warn\SWarn;
use OnixUtils\OnixUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use function array_map;
use function array_slice;
use function asort;
use function ceil;
use function count;
use function time;

class Content{

	protected string $name;

	/** @var Item[] */
	protected array $items = [];

	protected array $players = [];

	protected int $x;

	protected int $y;

	protected int $z;

	protected string $world;

	protected int $deadLine;

	/** @var int[] */
	protected array $startTimes = [];

	/** @var int[] */
	protected array $clearLog = [];

	/** @var string */
	protected string $warpName;

	public function __construct(string $name, array $items, array $players, int $x, int $y, int $z, string $world, int $deadLine, array $startTimes, array $clearLog, string $warpName){
		$this->name = $name;
		foreach($items as $item)
			$this->items[] = Item::jsonDeserialize($item);
		$this->players = $players;

		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->world = $world;
		$this->deadLine = $deadLine;
		$this->startTimes = $startTimes;
		$this->clearLog = $clearLog;
		$this->warpName = $warpName;
	}

	public function start(Player $player) : void{
		$this->startTimes[$player->getName()] = time();
		OnixUtils::message($player, "§d{$this->getName()}§f 컨텐츠를 시작합니다.");
		OnixUtils::message($player, "부적절한 방법으로 컨텐츠 클리어 시 제재를 받을 수 있습니다.");
	}

	public function getName() : string{
		return $this->name;
	}

	public function getWarpName() : string{
		return $this->warpName;
	}

	public function getPosition() : Position{
		return new Position($this->x, $this->y, $this->z, Server::getInstance()->getWorldManager()->getWorldByName($this->world));
	}

	public function getItems() : array{
		return $this->items;
	}

	public function getPlayers() : array{
		return $this->players;
	}

	public function equals(Content $that) : bool{
		return $this->getPosition()->equals($that->getPosition()) && $this->getItems() === $that->getItems() && $this->getPlayers() === $that->getPlayers() && $this->getName() === $that->getName();
	}

	public function addPlayer(Player $player, int $clearTime) : void{
		$this->players[$player->getName()] = time();
		if(isset($this->clearLog[$player->getName()])){
			if($this->clearLog[$player->getName()] < $clearTime){
				$this->clearLog[$player->getName()] = $clearTime;
			}
		}else{
			$this->clearLog[$player->getName()] = $clearTime;
		}
	}

	public function addItem(Item $item) : void{
		$this->items[] = $item;
	}

	public function canComplete(Player $player) : bool{
		//return !isset($this->players[$player->getName()]) or time() >= (($this->players[$player->getName()] ?? 0) + (60 * 60 * 60 * 24));
		if(!isset($this->startTimes[$player->getName()])){
			return false;
		}
		if(!isset($this->players[$player->getName()])){
			return true;
		}

		return time() >= ($this->players[$player->getName()] + (60 * 60 * 24));
	}

	public function complete(Player $player) : void{
		$startTime = $this->startTimes[$player->getName()] ?? -1;
		if($this->deadLine > 0 && $startTime > 0){
			if(($val = time() - $startTime) < $this->deadLine){
				SWarn::addWarn($player->getName(), 7, "컨텐츠 핵 ({$this->getName()} {$val}초)", "Content system");
				unset($this->startTimes[$player->getName()]);
				return;
			}
		}
		unset($this->startTimes[$player->getName()]);
		$this->addPlayer($player, ($val = time() - $startTime));
		Server::getInstance()->broadcastMessage("§d- - - - - - - - - - - - - - - - - - - - - -");
		OnixUtils::broadcast("§d" . $player->getName() . "§f님이 §d§l" . $this->getName() . " §r컨텐츠를 클리어 하셨습니다!");
		OnixUtils::broadcast("컨텐츠 클리어까지 걸린 시간: " . OnixUtils::convertTimeToString($val) . ", 순위: §d" . $this->getRank($player) . "§f위");
		Server::getInstance()->broadcastMessage("§d- - - - - - - - - - - - - - - - - - - - - -");
		//$player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());

		($ev = new ContentClearEvent($player, $this, $this->items))->call();

		if(count($ev->getRewards()) > 0){
			foreach($ev->getRewards() as $item)
				$player->getInventory()->addItem($item);
		}
	}

	public function setDeadLine(int $deadLine) : void{
		$this->deadLine = $deadLine;
	}

	public function getDeadLine() : int{
		return $this->deadLine;
	}

	public function getTopRanks(int $page) : array{
		asort($this->clearLog);
		if($page < 1)
			$page = 1;
		if($page > ($max = (int) ceil(count($this->clearLog) / 5)))
			$page = $max;

		return array_slice($this->clearLog, $page * 6, 5);
	}

	/**
	 * @param Player|string $player
	 *
	 * @return int
	 */
	public function getRank($player) : int{
		if($player instanceof Player)
			$player = $player->getName();
		asort($this->clearLog);
		$c = 0;
		foreach($this->clearLog as $name => $time){
			$c++;
			if($name === $player)
				return $c;
		}
		return -1;
	}

	public static function jsonDeserialize(array $data) : Content{
		return new Content((string) $data["name"], (array) $data["items"], (array) $data["players"], (int) $data["x"], (int) $data["y"], (int) $data["z"], (string) $data["world"], (int) $data["deadLine"], (array) $data["startTimes"], (array) $data["clearLog"], (string) $data["warpName"]);
	}

	public function jsonSerialize() : array{
		return [
			"name" => $this->name,
			"items" => array_map(function(Item $item) : array{
				return $item->jsonSerialize();
			}, $this->items),
			"players" => $this->players,
			"x" => $this->x,
			"y" => $this->y,
			"z" => $this->z,
			"world" => $this->world,
			"deadLine" => $this->deadLine,
			"startTimes" => $this->startTimes,
			"clearLog" => $this->clearLog,
			"warpName" => $this->warpName
		];
	}
}