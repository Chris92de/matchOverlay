<?php

/*
 * Plugin: matchOverlay
 * ~~~~~~~~~~~~~~~~~~~
 * Displays a splash on top of the screen with team scores
 *
 * ----------------------------------------------------------------------------------
 * Author:           Chris92, TheM
 * Version:          v0.4
 * Date:             2013-03-21
 * Copyright:        Christopher "Chris92" Flügel, Max "TheM" Klaversma
 * System:           XAseco/1.15b+ and XAseco2/1.01+
 * Game:             Trackmania Forever (TMF) / ManiaPlanet (MP)
 * ----------------------------------------------------------------------------------
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * ----------------------------------------------------------------------------------
 *
 * Dependencies:
 *  - none
 */

class mOverlay {
    var $gstate;
    var $state;
    var $score;
    var $teams;
    var $version = '0.4';
    var $to = 2; // 0 = Send to all, 1 = Only to players, 2 = Only to spectators
    var $close = false;
    var $timeout = 0;
    var $mlid = '9999999';
    var $display;

    function ovrly_onSync($aseco) {
        // Check for the right XAseco-Version
        $xaseco_min_version = '1.15.2';         // Official "1.15b", but not useable with version_compare()
        $xaseco2_min_version = '1.01';
        if(defined('XASECO_VERSION')) {
            $version = str_replace(
                array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'),
                array('.1','.2','.3','.4','.5','.6','.7','.8','.9'),
                XASECO_VERSION
            );
            if(version_compare($version, $xaseco_min_version, '<')) {
                trigger_error('[plugin.mOverlay.php] Not supported XAseco version ('. XASECO_VERSION .')! Please update to min. version '. $xaseco_min_version .'!', E_USER_ERROR);
            }
        } else {
            if(defined('XASECO2_VERSION')) {
                $version = str_replace(
                    array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i'),
                    array('.1','.2','.3','.4','.5','.6','.7','.8','.9'),
                    XASECO2_VERSION
                );
                if(version_compare($version, $xaseco2_min_version, '<')) {
                    trigger_error('[plugin.mOverlay.php] Not supported XAseco2 version ('. XASECO2_VERSION .')! Please update to min. version '. $xaseco2_min_version .'!', E_USER_ERROR);
                }
            } else {
                trigger_error('[plugin.mOverlay.php] Can not identify the System, "XASECO_VERSION" and "XASECO2_VERSION" are unset! This plugin runs only with XAseco/'. $xaseco_min_version .' and up and XAseco2/'. $xaseco2_min_version .' and up.', E_USER_ERROR);
            }
        }

        // Register this to the global version pool (for up-to-date checks)
        $aseco->plugin_versions[] = array(
            'plugin'    => 'plugin.mOverlay.php',
            'author'    => 'Chris92',
            'version'   => $this->version
        );

        $this->gstate['GameState'] = 'race';

        $this->teams = array('Team Blue', 'Team Red');                      // Teamnames
        $this->score = array(0, 0);                                         // Initial overall score
        $this->state = false;                                               // true = autostart, false = start with command
        $this->ovrly_readXML($aseco);

        $message = '{#server}>> {#highlite}mOverlay plugin '.$this->version.' loaded';
        $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
    }

    function ovrly_onPlayerConnect($aseco, $player) {
        if($this->to == 0) {
            //Send to all players
            $this->ovrly_show($aseco, $player->login);
        } elseif($this->to == 1) {
            //send only to players
            if($aseco->isPlayer($player->login))
                $this->ovrly_show($aseco, $player->login);
        } elseif($this->to == 2) {
            //Send only to spectators
            if($aseco->isSpectator($player))
                $this->ovrly_show($aseco, $player->login);
        }
    }

    function ovrly_onPlayerInfoChanged($aseco, $changes) {
        if($this->gstate['GameState'] == 'race') {
            if($this->to == 2) {
                if($changes['SpectatorStatus'] == 0) {
                    $this->ovrly_hide($aseco, $changes['Login']);
                } elseif($changes['SpectatorStatus'] > 0) {
                    $this->ovrly_show($aseco, $changes['Login']);
                }
            }
            if($this->to == 1) {
                if($changes['SpectatorStatus'] > 0) {
                    $this->ovrly_hide($aseco, $changes['Login']);
                } elseif($changes['SpectatorStatus'] == 0) {
                    $this->ovrly_show($aseco, $changes['Login']);
                }
            }
        } else {
            return;
        }
    }

    function ovrly_onNewChallenge($aseco) {
        $this->gstate['GameState'] = 'race';
        if ($aseco->server->gameinfo->mode != Gameinfo::TEAM && $this->state == true){
            $this->state = false;
            $msg = 'Plugin mOverlay disabled because game mode is no longer TEAM';
            $aseco->client->query('ChatSendServerMessage', $msg);
            foreach($aseco->server->players->player_list as $player) {
                $this->ovrly_hide($aseco, $player->login);}
        } else {
            $this->ovrly_updateAll($aseco);
        }
    }

    function ovrly_onRestartChallenge2($aseco) {
        $this->gstate['GameState'] = 'race';
    }

    function ovrly_onEndRace($aseco, $race) {
        $this->gstate['GameState'] = 'score';

        foreach($aseco->server->players->player_list as $player) {
            $this->ovrly_hide($aseco, $player->login);
        }
    }

    /** Chat function **/
    function chat_moverlay($aseco, $command) {
        $admin = $command['author'];
        $login = $admin->login;
        $nick = $admin->nickname;

        $cmd = explode(' ', $command['params']);

        if($aseco->isMasterAdmin($admin) || $aseco->isAdmin($admin)) {
            if($cmd[0] == 'team') {
                $this->teams[($cmd[1]-1)] = substr(str_replace('team ', '', $command['params']), 1);
                $msg = 'Team '.$cmd[1].' is now named '.$this->teams[($cmd[1]-1)].'.';
            } elseif($cmd[0] == 'mscore') {
                if(is_numeric($cmd[1]) && is_numeric($cmd[2])) {
                    $this->score = array($cmd[1], $cmd[2]);
                    $msg = 'Match score set to '.$cmd[1].' - '.$cmd[2].'.';
                } else {
                    $msg = 'The score you entered is not numeric!';
                }
            } elseif($cmd[0] == 'activate' || $cmd[0] == 'enable') {
                if ($aseco->server->gameinfo->mode == Gameinfo::TEAM){
                    $this->state = true;
                    $msg = 'Plugin matchOverlay has been activated.';
                } else {
                    $msg = 'matchOverlay only works in TEAM mode.';
                    $this->state = false;
                }

            } elseif($cmd[0] == 'disable') {
                $this->state = false;
                $msg = 'Plugin matchOverlay has been disabled.';
                foreach($aseco->server->players->player_list as $player)
                    $this->ovrly_hide($aseco, $player->login);
            } elseif($cmd[0] == 'to') {
                if($cmd[1] == 'players') {
                    $this->to = 1;
                } elseif($cmd[1] == 'spectators') {
                    $this->to = 2;
                } else {
                    $this->to = 0;
                }

                $s = array("everyone connected", "players", "spectators");
                $msg = 'Displaying matchOverlay to '.$s[$this->to].'.';
            } elseif($cmd[0] == 'about') {
                $msg = 'Inspired by the original ESL TV tSplash plugin for FAST Aseco by Sven Stucki, matchOverlay gives you a nice overlay which can be used not only for streams. (by Chris92 & TheM)';
            }

            if($this->state && $cmd[0] != 'about' && $cmd[0] != 'disable') {
                $this->ovrly_updateAll($aseco);
            }

            if($msg != '') {
                $aseco->client->query('ChatSendServerMessageToLogin', $msg, $login);
            }
        }
    }

    /** Helper functions ... **/
    function ovrly_readXML($aseco) {
        if($settings = $aseco->xml_parser->parseXml('moverlay.xml', true, CONFIG_UTF8ENCODE)) {
            // read the XML structure into an array
            $moverlay = $settings['MOVERLAY'];
            $msettings = $moverlay['SETTINGS'][0];
            $mteams = $moverlay['TEAMS'][0];
            $mdisplay = $moverlay['DISPLAY'][0];

            if($msettings['AUTOENABLE'][0] == 'true') {
                $this->state = true;
            }

            $to = $msettings['TO'][0];
            if($to == 'all') {
                $this->to = 0;
            } elseif($to == 'players') {
                $this->to = 1;
            } elseif($to == 'spectators') {
                $this->to = 2;
            }

            $this->teams = array($mteams['TEAM_BLUE'][0], $mteams['TEAM_RED'][0]);

            $display = new stdClass();
            $display->bg = new stdClass();
            $display->t1 = new stdClass();
            $display->t2 = new stdClass();
            $display->s1 = new stdClass();
            $display->s2 = new stdClass();
            $display->soverall = new stdClass();

            $display->bg->image = $mdisplay['BG_IMAGE'][0];
            $display->bg->pos_x = $mdisplay['BG_POS_X'][0];
            $display->bg->pos_y = $mdisplay['BG_POS_Y'][0];

            $display->t1->pos_x = $mdisplay['T1_POS_X'][0];
            $display->t1->pos_y = $mdisplay['T1_POS_Y'][0];
            $display->t2->pos_x = $mdisplay['T2_POS_X'][0];
            $display->t2->pos_y = $mdisplay['T2_POS_Y'][0];

            $display->s1->pos_x = $mdisplay['S1_POS_X'][0];
            $display->s1->pos_y = $mdisplay['S1_POS_Y'][0];
            $display->s2->pos_x = $mdisplay['S2_POS_X'][0];
            $display->s2->pos_y = $mdisplay['S2_POS_Y'][0];
            $display->soverall->pos_x = $mdisplay['SOVERALL_POS_X'][0];
            $display->soverall->pos_y = $mdisplay['SOVERALL_POS_Y'][0];

            $this->display = $display;
        } else {
            // could not parse XML file
            trigger_error('Could not read/parse config file moverlay.xml !', E_USER_ERROR);
        }
    }

    function ovrly_updateAll($aseco) {
        if($this->to == 0) {
            //Send to all players
            foreach($aseco->server->players->player_list as $player) {
                $this->ovrly_show($aseco, $player->login);
            }
        } elseif($this->to == 1) {
            //send only to players
            foreach($aseco->server->players->player_list as $player) {
                if(!$aseco->isSpectator($player))
                    $this->ovrly_show($aseco, $player->login);
            }
        } elseif($this->to == 2) {
            //Send only to spectators
            foreach($aseco->server->players->player_list as $player) {
                if($aseco->isSpectator($player)) {
                    $this->ovrly_show($aseco, $player->login);
                }
            }
        }
    }

    function ovrly_show($aseco, $login) {
        if(!$this->state) return;

	if(defined('XASECO2_VERSION')) {
	$aseco->client->query('GetCurrentWinnerTeam');
	$winningteam = $aseco->client->getResponse();

	if($winningteam>0){
	$aseco->client->query('GetCurrentRanking', 2, 0);
        $tscore = $aseco->client->getResponse();

        $xml = '<manialinks>
        <manialink id='.$this->mlid.'>
         <quad posn="'.$this->display->bg->pos_x.' '.$this->display->bg->pos_y.' 0" sizen="85 11.3" image="'.$this->display->bg->image.'" />
        <label posn="'.$this->display->s1->pos_x.' '.$this->display->s1->pos_y.' 1" sizen="19.8 5" style="TextRaceChrono" halign="center" valign="center" textsize="8" textcolor="FFFF" text="'.$tscore[1]['Score'].'"></label>
        <label posn="'.$this->display->s2->pos_x.' '.$this->display->s2->pos_y.' 1" sizen="19.8 5" style="TextRaceChrono" halign="center" valign="center" textsize="8" textcolor="FFFF" text="'.$tscore[0]['Score'].'"></label>
        <label posn="'.$this->display->soverall->pos_x.' '.$this->display->soverall->pos_y.' 1" sizen="19.8 5" style="TextTitle3" halign="center" valign="center" textsize="2" textcolor="FFFF" text="'.$this->score[0].' - '.$this->score[1].'"></label>
        <label posn="'.$this->display->t1->pos_x.' '.$this->display->t1->pos_y.' 1" style="TextRankingsBig" valign="center" halign="left" sizen="39 4" textsize="4" textcolor="FFFF" text="'.$this->teams[0].'"></label>
        <label posn="'.$this->display->t2->pos_x.' '.$this->display->t2->pos_y.' 1" style="TextRankingsBig" valign="center" halign="right" sizen="20.8 3.6" textsize="4" textcolor="FFFF" text="'.$this->teams[1].'"></label>
        </manialink>
        </manialinks>';
	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, ($this->timeout * 1000), $this->close);
        $aseco->console('[mOverlay] Showing overlay to {1}!', $login);
        }
	else {
	$aseco->client->query('GetCurrentRanking', 2, 0);
        $tscore = $aseco->client->getResponse();

        $xml = '<manialinks>
        <manialink id='.$this->mlid.'>
         <quad posn="'.$this->display->bg->pos_x.' '.$this->display->bg->pos_y.' 0" sizen="85 11.3" image="'.$this->display->bg->image.'" />
        <label posn="'.$this->display->s1->pos_x.' '.$this->display->s1->pos_y.' 1" sizen="19.8 5" style="TextRaceChrono" halign="center" valign="center" textsize="8" textcolor="FFFF" text="'.$tscore[0]['Score'].'"></label>
        <label posn="'.$this->display->s2->pos_x.' '.$this->display->s2->pos_y.' 1" sizen="19.8 5" style="TextRaceChrono" halign="center" valign="center" textsize="8" textcolor="FFFF" text="'.$tscore[1]['Score'].'"></label>
        <label posn="'.$this->display->soverall->pos_x.' '.$this->display->soverall->pos_y.' 1" sizen="19.8 5" style="TextTitle3" halign="center" valign="center" textsize="2" textcolor="FFFF" text="'.$this->score[0].' - '.$this->score[1].'"></label>
        <label posn="'.$this->display->t1->pos_x.' '.$this->display->t1->pos_y.' 1" style="TextRankingsBig" valign="center" halign="left" sizen="39 4" textsize="4" textcolor="FFFF" text="'.$this->teams[0].'"></label>
        <label posn="'.$this->display->t2->pos_x.' '.$this->display->t2->pos_y.' 1" style="TextRankingsBig" valign="center" halign="right" sizen="20.8 3.6" textsize="4" textcolor="FFFF" text="'.$this->teams[1].'"></label>
        </manialink>
        </manialinks>';
	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, ($this->timeout * 1000), $this->close);
        $aseco->console('[mOverlay] Showing overlay to {1}!', $login);
	}}
	else {
		$aseco->client->query('GetCurrentRanking', 2, 0);
        $tscore = $aseco->client->getResponse();

        $xml = '<manialinks>
        <manialink id='.$this->mlid.'>
         <quad posn="'.$this->display->bg->pos_x.' '.$this->display->bg->pos_y.' 0" sizen="85 11.3" image="'.$this->display->bg->image.'" />
        <label posn="'.$this->display->s1->pos_x.' '.$this->display->s1->pos_y.' 1" sizen="19.8 5" style="TextRaceChrono" halign="center" valign="center" textsize="8" textcolor="FFFF" text="'.$tscore[0]['Score'].'"></label>
        <label posn="'.$this->display->s2->pos_x.' '.$this->display->s2->pos_y.' 1" sizen="19.8 5" style="TextRaceChrono" halign="center" valign="center" textsize="8" textcolor="FFFF" text="'.$tscore[1]['Score'].'"></label>
        <label posn="'.$this->display->soverall->pos_x.' '.$this->display->soverall->pos_y.' 1" sizen="19.8 5" style="TextTitle3" halign="center" valign="center" textsize="2" textcolor="FFFF" text="'.$this->score[0].' - '.$this->score[1].'"></label>
        <label posn="'.$this->display->t1->pos_x.' '.$this->display->t1->pos_y.' 1" style="TextRankingsBig" valign="center" halign="left" sizen="39 4" textsize="4" textcolor="FFFF" text="'.$this->teams[0].'"></label>
        <label posn="'.$this->display->t2->pos_x.' '.$this->display->t2->pos_y.' 1" style="TextRankingsBig" valign="center" halign="right" sizen="20.8 3.6" textsize="4" textcolor="FFFF" text="'.$this->teams[1].'"></label>
        </manialink>
        </manialinks>';
	$aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, ($this->timeout * 1000), $this->close);
        $aseco->console('[mOverlay] Showing overlay to {1}!', $login);
	}
    }

    function ovrly_hide($aseco, $login) {
        $xml = '<manialink id='.$this->mlid.'></manialink>';
        $aseco->client->query('SendDisplayManialinkPageToLogin', $login, $xml, (1 * 1000), $this->close);
        $aseco->console('[mOverlay] Hiding overlay from {1} ', $login);
    }
}

global $mo;
$mo = new mOverlay();

Aseco::registerEvent('onSync',                   array($mo, 'ovrly_onSync'));
Aseco::registerEvent('onPlayerConnect',          array($mo, 'ovrly_onPlayerConnect'));
Aseco::registerEvent('onEndRound',               array($mo, 'ovrly_updateAll'));
Aseco::registerEvent('onNewChallenge',           array($mo, 'ovrly_onNewChallenge'));
Aseco::registerEvent('onNewMap',                 array($mo, 'ovrly_onNewChallenge'));
Aseco::registerEvent('onPlayerInfoChanged',      array($mo, 'ovrly_onPlayerInfoChanged'));
Aseco::registerEvent('onEndMap',                 array($mo, 'ovrly_onEndRace')); 
Aseco::registerEvent('onEndRace',                array($mo, 'ovrly_onEndRace'));
Aseco::registerEvent('onRestartMap',             array($mo, 'ovrly_onRestartChallenge2'));
Aseco::registerEvent('onRestartChallenge2',      array($mo, 'ovrly_onRestartChallenge2'));

Aseco::addChatCommand('moverlay', 'Command to modify the matchOverlay overlay');

function chat_moverlay($aseco, $command) {
    global $mo;
    $mo->chat_moverlay($aseco, $command);
}
?>