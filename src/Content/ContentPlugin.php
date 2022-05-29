<?php
declare(strict_types=1);

namespace Content;

use Content\form\ContentInfoForm;
use OnixUtils\OnixUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use solo\swarp\event\PlayerWarpEvent;
use function array_map;
use function array_values;
use function count;
use function implode;
use function intval;
use function is_int;
use function is_numeric;
use function strpos;
use function time;
use function trim;

class ContentPlugin extends PluginBase implements Listener{

	/** @var Content[] */
	protected array $contents = [];

	protected array $mode = [];

	/** @var Config */
	protected Config $config;

	protected array $db = [];

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->config = new Config($this->getDataFolder() . "ContentData.yml", Config::YAML, []);
		$this->db = $this->config->getAll();

		foreach($this->db as $name => $data){
			$content = Content::jsonDeserialize($data);
			$this->contents[$content->getName()] = $content;
		}
	}

	protected function onDisable() : void{
		$arr = [];
		foreach(array_values($this->contents) as $content){
			$arr[$content->getName()] = $content->jsonSerialize();
		}

		$this->config->setAll($arr);
		$this->config->save();
	}

	/**
	 * @param PlayerInteractEvent $event
	 *
	 * @handleCancelled true
	 */
	public function handleInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();

		if(isset($this->mode[$player->getName()])){
			$name = $this->mode[$player->getName()]["name"];
			$warpName = $this->mode[$player->getName()]["warp"];
			$content = new Content($name, [], [], $event->getBlock()->getPosition()->getFloorX(), $event->getBlock()->getPosition()->getFloorY(), $event->getBlock()->getPosition()->getFloorZ(), $event->getBlock()->getPosition()->getWorld()->getFolderName(), -1, [], [], $warpName);
			$this->addContent($content);

			/*
			SWarp::getInstance()->addWarp(new Warp(
				$name,
				$event->getBlock()->getFloorX(),
				$event->getBlock()->getFloorY(),
				$event->getBlock()->getFloorZ(),
				$event->getBlock()->getLevel()->getFolderName()
			));
			*/

			//$warp = SWarp::getInstance()->getWarp($name);

			//$warp->setOptions(array_merge($warp->getOptions(), [
			//	new TitleOption(new ArgumentString("§d§l<§f " . $name . " §d>"))
			//]));
			OnixUtils::message($player, "컨텐츠를 생성하였습니다.");
			unset($this->mode[$player->getName()]);
			return;
		}

		if(($content = $this->getContentByPos($event->getBlock()->getPosition())) instanceof Content){
			if($content->canComplete($player)){
				$content->complete($player);
			}else{
				//$player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
				OnixUtils::message($player, "오늘은 더 이상 이 컨텐츠를 클리어 할 수 없습니다.");
				if(isset($content->getPlayers()[$player->getName()])){
					OnixUtils::message($player, "남은 시간: " . OnixUtils::convertTimeToString(($content->getPlayers()[$player->getName()] + (60 * 60 * 24)) - time()));
				}
			}
		}
	}

	public function onPlayerWarp(PlayerWarpEvent $event) : void{
		$warp = $event->getWarp();
		foreach($this->getContents() as $content){
			if($content->getWarpName() === $warp->getName()){
				$content->start($event->getPlayer());
			}
		}
	}

	public function handlePlayerQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();

		if(isset($this->mode[$player->getName()])){
			unset($this->mode[$player->getName()]);
		}
	}

	public function addContent(Content $content) : void{
		$this->contents[$content->getName()] = $content;
	}

	public function removeContent(Content $content) : void{
		unset($this->contents[$content->getName()]);
	}

	public function getContent(string $name) : ?Content{
		return $this->contents[$name] ?? null;
	}

	public function getContentByPos(Position $pos) : ?Content{
		foreach($this->getContents() as $content){
			if($content->getPosition()->equals($pos))
				return $content;
		}
		return null;
	}

	/**
	 * @return Content[]
	 */
	public function getContents() : array{
		return array_values($this->contents);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "컨텐츠":
				switch($args[0] ?? "x"){
					case "생성":
					case "create":
						if(trim($args[1] ?? "") !== ""){
							if(trim($args[2] ?? "") !== ""){
								if(!$this->getContent($args[1]) instanceof Content){
									$this->mode[$sender->getName()] = ["name" => $args[1], "warp" => $args[2]];
									OnixUtils::message($sender, "컨텐츠 클리어 지점을 클릭해주세요.");
								}else{
									OnixUtils::message($sender, "해당 이름의 컨텐츠가 이미 존재합니다.");
								}
							}
						}else{
							OnixUtils::message($sender, "/컨텐츠 생성 [이름] [워프이름] - 컨텐츠를 생성합니다. (워프이름은 컨텐츠 시작 시 사용될 워프 이름을 입력해야하며, 생성되어 있어야 함)");
						}
						break;
					case "제거":
					case "remove":
						if(trim($args[1] ?? "") !== ""){
							if(($content = $this->getContent($args[1])) instanceof Content){
								$this->removeContent($content);
								OnixUtils::message($sender, "컨텐츠를 제거하였습니다.");
								//SWarp::getInstance()->removeWarp(SWarp::getInstance()->getWarp($content->getName()));
							}else{
								OnixUtils::message($sender, "해당 이름의 컨텐츠가 존재하지 않습니다.");
							}
						}else{
							OnixUtils::message($sender, "/컨텐츠 제거 [이름] - 컨텐츠를 제거합니다.");
						}
						break;
					case "목록":
					case "list":
						if(count($this->contents) > 0){
							OnixUtils::message($sender, "컨텐츠 목록: " . implode(", ", array_map(function(Content $content) : string{
									return $content->getName();
								}, $this->getContents())));
						}else{
							OnixUtils::message($sender, "컨텐츠 목록이 존재하지 않습니다.");
						}
						break;
					case "보상추가":
					case "additem":
						if($sender instanceof Player){
							if(trim($args[1] ?? "") !== ""){
								if(($content = $this->getContent($args[1])) instanceof Content){
									$item = $sender->getInventory()->getItemInHand();
									if(!$item->isNull()){
										$content->addItem($item);
										OnixUtils::message($sender, "컨텐츠에 보상을 추가했습니다.");
									}else{
										OnixUtils::message($sender, "아이템의 아이디는 공기가 아니어야 합니다.");
									}
								}else{
									OnixUtils::message($sender, "해당 이름의 컨텐츠가 존재하지 않습니다.");
								}
							}else{
								OnixUtils::message($sender, "/컨텐츠 보상추가 [이름] - 컨텐츠에 내가 든 아이템을 보상으로 추가합니다.");
							}
						}
						break;
					case "정보":
					case "info":
						if(trim($args[1] ?? "") !== ""){
							if(($content = $this->getContent($args[1])) instanceof Content){
								$sender->sendMessage("§d===== §f[ " . $content->getName() . " ] §d=====");
								OnixUtils::message($sender, "컨텐츠 보상 목록: " . implode(", ", array_map(function(Item $item) : string{
										return $item->getName() . " " . $item->getCount() . "개";
									}, $content->getItems())));
								OnixUtils::message($sender, "위치: " . OnixUtils::posToStr($content->getPosition()));
								OnixUtils::message($sender, "데드라인: " . ($content->getDeadLine() > 0 ? OnixUtils::convertTimeToString($content->getDeadLine()) : "없음"));
							}else{
								OnixUtils::message($sender, "해당 이름의 컨텐츠가 존재하지 않습니다.");
							}
						}else{
							OnixUtils::message($sender, "/컨텐츠 정보 [이름] - 컨텐츠의 정보를 봅니다.");
						}
						break;
					case "데드라인":
						if(trim($args[1] ?? "") !== ""){
							if(trim($args[2] ?? "") !== "" && is_numeric($args[2])){
								if(($content = $this->getContent($args[1])) instanceof Content){
									$content->setDeadLine(intval($args[2]));
									OnixUtils::message($sender, "{$content->getName()} 컨텐츠의 데드라인을 {$args[2]}초로 설정했습니다.");
								}else{
									OnixUtils::message($sender, "해당 이름의 컨텐츠가 존재하지 않습니다.");
								}
							}else{
								OnixUtils::message($sender, "/컨텐츠 데드라인 [이름] [데드라인(초)] - 컨텐츠의 데드라인을 설정합니다.");
							}
						}else{
							OnixUtils::message($sender, "/컨텐츠 데드라인 [이름] [데드라인(초)] - 컨텐츠의 데드라인을 설정합니다.");
						}
						break;
					default:
						OnixUtils::message($sender, "/컨텐츠 생성 [이름] [워프이름] - 컨텐츠를 생성합니다. (워프이름은 컨텐츠 시작 시 사용될 워프 이름을 입력해야하며, 생성되어 있어야 함)");
						OnixUtils::message($sender, "/컨텐츠 제거 [이름] - 컨텐츠를 제거합니다.");
						OnixUtils::message($sender, "/컨텐츠 목록 - 컨텐츠 목록을 봅니다.");
						OnixUtils::message($sender, "/컨텐츠 보상추가 [이름] - 컨텐츠에 내가 든 아이템을 보상응로 추가합니다.");
						OnixUtils::message($sender, "/컨텐츠 정보 [이름] - 컨텐츠의 정보를 봅니다.");
						OnixUtils::message($sender, "/컨텐츠 데드라인 [이름] [데드라인(초)] - 컨텐츠의 데드라인을 설정합니다.");
				}
				break;
			case "컨텐츠기록":
				if($sender instanceof Player){
					if(trim($args[0] ?? "") !== ""){
						if(($content = $this->getContent($args[0])) instanceof Content){
							$sender->sendForm(new ContentInfoForm($content));
						}else{
							OnixUtils::message($sender, "해당 이름의 컨텐츠가 존재하지 않습니다.");
						}
					}else{
						OnixUtils::message($sender, "/컨텐츠기록 [컨텐츠] - 컨텐츠의 기록(순위)를 봅니다.");
					}
				}
				break;
		}
		return true;
	}

	public function onDataPacketSend(DataPacketSendEvent $event) : void{
		$packets = $event->getPackets();
		foreach($packets as $packet){
			if($packet instanceof AvailableCommandsPacket){
				if(isset($packet->commandData["컨텐츠기록"])){
					$data = $packet->commandData["컨텐츠기록"];
					$parameter = new CommandParameter();
					$parameter->paramName = "contentName";
					$parameter->isOptional = false;
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
					$parameter->enum = new CommandEnum("ContentName", array_map(function(Content $content) : string{
						$name = $content->getName();
						if(is_int(strpos($name, " "))){
							$name = '"' . $name . '"';
						}
						return $name;
					}, array_values($this->contents)));
					$data->overloads = [[$parameter]];
					$packet->commandData["컨텐츠기록"] = $data;
				}
			}
		}
	}
}