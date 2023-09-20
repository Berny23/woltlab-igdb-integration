/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2023 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */

import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import * as Language from "WoltLabSuite/Core/Language";
import * as UiNotification from "WoltLabSuite/Core/Ui/Notification";

//let gameUserEditDialog: FormBuilderDialog;

interface ReturnValues {
	gameId: number;
	playerCount: number;
	ownRating: number;
	isOwned: boolean;
}

export function init(gameId: number, userId: number) {
	var gameUserEditDialog = new FormBuilderDialog(
		'gameUserEditDialog' + gameId,
		'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
		'getGameUserEditDialog', {
		destroyOnClose: true,
		actionParameters: {
			gameId: gameId,
			userId: userId
		},
		dialog: {
			title: Language.get('wcf.igdb_integration.dialog.game_user_edit_title')
		},
		submitActionName: 'submitGameUserEditDialog',
		successCallback(returnValues: ReturnValues) {
			if (returnValues.playerCount <= 0) {
				// Remove game from profile list

				document.getElementById('gameBox' + returnValues.gameId)?.remove();
			} else {
				// Insert returned values into page

				var ratingElement = document.querySelector('#gameBox' + returnValues.gameId +
					' .gameOwnRating')
				var playersElement = document.getElementById('gamePlayerCount' + returnValues.gameId);

				if (ratingElement !== null && playersElement !== null) {
					ratingElement.innerHTML = '';
					playersElement.style.display = returnValues.playerCount <= 0 ? 'none' : '';

					for (let i = 0; i < returnValues.ownRating; i++) {
						// Add star icon
						const starIcon = document.createElement('fa-icon');
						starIcon.size = 16;
						starIcon.setIcon('star', true);
						ratingElement.appendChild(starIcon);
					}

					if (returnValues.isOwned) {
						playersElement.classList.add('isOwned');
					} else {
						playersElement.classList.remove('isOwned');
					}
				}
			}
		}
	}
	);

	document.getElementById('gameOverlay' + gameId)?.addEventListener('click', function () {
		gameUserEditDialog.open();
	});
}