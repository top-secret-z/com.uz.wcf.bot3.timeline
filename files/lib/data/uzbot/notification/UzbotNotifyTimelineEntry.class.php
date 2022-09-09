<?php
namespace timeline\data\uzbot\notification;
use timeline\data\entry\EntryEditor;
use wcf\data\uzbot\Uzbot;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\exception\SystemException;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\MessageUtil;

/**
 * Creates timeline entries for Bot
 * 
 * @author		2018-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.timeline
 */
class UzbotNotifyTimelineEntry {
	public function send(Uzbot $bot, $content, $subject, $teaser, $language, $receiver, $tags) {
		// prepare text and data
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		
		$subject = $teaser = '';
		$content = MessageUtil::stripCrap($content);
		
		// set publication time
		$publicationTime = TIME_NOW;
		if (isset($bot->publicationTime) && $bot->publicationTime) {
			$publicationTime = $bot->publicationTime;
		}
		
		if (!$bot->testMode) {
			$htmlInputProcessor = new HtmlInputProcessor();
			$htmlInputProcessor->process($content, 'de.darkwood-studios.timeline.entry', 0);
			
			// create entry
			try {
				
				$data = [
						'message' => $htmlInputProcessor->getHtml(),
						'typeID' => 0,
						'timelineUserID' => $receiver->userID,
						'userID' => $bot->senderID,
						'username' => $bot->sendername,
						'time' => $publicationTime,
						'additionalData' => serialize([]),
						'isShare' => 0
				];
				
				$entry = EntryEditor::create($data);
			}
			catch (SystemException $e) {
				// users may get lost; check sender again to abort
				if (!$bot->checkSender(true, true)) return false;
				
				// report any other error und continue
				if ($bot->enableLog) {
					$error = $defaultLanguage->get('wcf.acp.uzbot.log.notify.error') . ' ' . $e->getMessage();
					
					UzbotLogEditor::create([
							'bot' => $bot,
							'status' => 1,
							'count' => 1,
							'additionalData' => $error
					]);
				}
			}
		}
		else {
			if (mb_strlen($content) > 63500) $content = mb_substr($content, 0, 63500) . ' ...';
			$result = serialize([$subject, $teaser, $content]);
			
			UzbotLogEditor::create([
					'bot' => $bot,
					'count' => 1,
					'testMode' => 1,
					'additionalData' => $result
			]);
		}
	}
}
