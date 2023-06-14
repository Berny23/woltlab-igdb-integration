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
import * as UiNotification from "WoltLabSuite/Core/Ui/Notification";

interface ReturnValues {
	gameId: number;
	playerCount: number;
	averageRating: number;
	isOwned: boolean;
}

export function init(gameId: number) {
	var gameUserEditDialog = new FormBuilderDialog(
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
		successCallback(returnValues: ReturnValues) {
			// Insert returned values into page

			var ratingElement = document.querySelector('#gameBox' + returnValues.gameId + ' .gameAverageRating');
			var playersElement = document.getElementById('gamePlayerCount' + returnValues.gameId);

			if (ratingElement !== null && playersElement !== null) {
				ratingElement.innerHTML = '';
				playersElement.style.display = returnValues.playerCount <= 0 ? 'none' : '';
				playersElement.innerHTML = '<span class="icon fa-user"></span> ' +
					returnValues.playerCount;

				for (let i = 0; i < returnValues.averageRating; i++) {
					ratingElement.innerHTML += '<span class="icon icon16 fa-star orange"></span>';
				}

				if (returnValues.isOwned) {
					playersElement.classList.add('isOwned');
				} else {
					playersElement.classList.remove('isOwned');
				}
			}
		}
	}
	);

	document.getElementById('gameOverlay' + gameId)?.addEventListener('click', function () {
		gameUserEditDialog.open();
	});

	var gamePlayerListDialog = new FormBuilderDialog(
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
		}
	);

	document.getElementById('gamePlayerCount' + gameId)?.addEventListener('click', function() {
		gamePlayerListDialog.open();
	});
}