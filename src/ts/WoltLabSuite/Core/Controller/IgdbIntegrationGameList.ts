/**
 * Provides the script features for the game list page.
 *
 * @author		Berny23
 * @copyright	2023 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameList
 */

import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import * as Language from "WoltLabSuite/Core/Language";
import { show as showNotification } from "WoltLabSuite/Core/Ui/Notification";

interface Response {
	gameId: number;
	playerCount: number;
	averageRating: number;
	isOwned: boolean;
}

async function showGameUserEditDialog(gameId: number) {
	// Call dialog form 
	let form = new FormBuilderDialog(
		'gameUserEditDialog' + gameId,
		'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
		'getGameUserEditDialog', {
		destroyOnClose: true,
		actionParameters: {
			gameId: gameId,
		},
		dialog: {
			title: Language.get('wcf.igdb_integration.dialog.game_user_edit_title')
		},
		submitActionName: 'submitGameUserEditDialog',
		successCallback(response: Response) {
			// Insert returned values into page
			var ratingElement = document.querySelector('#gameBox' + response.gameId + ' .gameAverageRating');
			var playersElement = document.getElementById('gamePlayerCount' + response.gameId);

			if (ratingElement !== null && playersElement !== null) {
				ratingElement.innerHTML = '';
				playersElement.innerHTML = '';
				playersElement.style.display = response.playerCount <= 0 ? 'none' : '';
				// Add user icon
				const userIcon = document.createElement('fa-icon');
				userIcon.size = 16;
				userIcon.setIcon('user', true);
				playersElement.appendChild(userIcon);
				playersElement.innerHTML += ' ' + response.playerCount;

				for (let i = 0; i < response.averageRating; i++) {
					// Add star icon
					const starIcon = document.createElement('fa-icon');
					starIcon.size = 16;
					starIcon.setIcon('star', true);
					ratingElement.appendChild(starIcon);
				}

				if (response.isOwned) {
					playersElement.classList.add('isOwned');
				} else {
					playersElement.classList.remove('isOwned');
				}
			}
			showNotification();
		}
	});

	form.open();
}

async function showGamePlayerListDialog(gameId: number) {
	let form = new FormBuilderDialog(
		'gamePlayerListDialog' + gameId,
		'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
		'getGamePlayerListDialog', {
		destroyOnClose: true,
		actionParameters: {
			gameId: gameId,
		},
		dialog: {
			title: Language.get('wcf.igdb_integration.dialog.game_player_list_title')
		}
	});

	form.open();
}

export function init(gameId: number) {
	document.getElementById('gameOverlay' + gameId)?.addEventListener('click', function () {
		showGameUserEditDialog(gameId);
	});
	document.getElementById('gamePlayerCount' + gameId)?.addEventListener('click', function () {
		showGamePlayerListDialog(gameId);
	});
}