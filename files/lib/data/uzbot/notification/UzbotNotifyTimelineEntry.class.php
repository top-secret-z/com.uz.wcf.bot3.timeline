<?php

/*
 * Copyright by Udo Zaydowicz.
 * Modified by SoftCreatR.dev.
 *
 * License: http://opensource.org/licenses/lgpl-license.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
namespace timeline\data\uzbot\notification;

use timeline\data\entry\EntryEditor;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\data\uzbot\Uzbot;
use wcf\system\exception\SystemException;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\MessageUtil;

/**
 * Creates timeline entries for Bot
 */
class UzbotNotifyTimelineEntry
{
    public function send(Uzbot $bot, $content, $subject, $teaser, $language, $receiver, $tags)
    {
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
                    'additionalData' => \serialize([]),
                    'isShare' => 0,
                ];

                $entry = EntryEditor::create($data);
            } catch (SystemException $e) {
                // users may get lost; check sender again to abort
                if (!$bot->checkSender(true, true)) {
                    return false;
                }

                // report any other error und continue
                if ($bot->enableLog) {
                    $error = $defaultLanguage->get('wcf.acp.uzbot.log.notify.error') . ' ' . $e->getMessage();

                    UzbotLogEditor::create([
                        'bot' => $bot,
                        'status' => 1,
                        'count' => 1,
                        'additionalData' => $error,
                    ]);
                }
            }
        } else {
            if (\mb_strlen($content) > 63500) {
                $content = \mb_substr($content, 0, 63500) . ' ...';
            }
            $result = \serialize([$subject, $teaser, $content]);

            UzbotLogEditor::create([
                'bot' => $bot,
                'count' => 1,
                'testMode' => 1,
                'additionalData' => $result,
            ]);
        }
    }
}
