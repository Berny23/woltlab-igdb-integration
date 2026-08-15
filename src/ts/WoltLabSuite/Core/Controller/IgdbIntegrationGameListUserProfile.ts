/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { show as showNotification } from "WoltLabSuite/Core/Ui/Notification";
import User from "WoltLabSuite/Core/User";

//let gameUserEditDialog: FormBuilderDialog;

interface ReturnValues {
	gameId: number;
	playerCount: number;
	ownRating: number;
	isOwned: boolean;
	gameCount: number;
}

function updateGameCount(gameCount: number) {
	const countElement = document.getElementById('igdbIntegrationGameCount');
	if (countElement !== null) {
		countElement.textContent = getPhrase('wcf.user.option.igdb_integration_game_count') + ': ' + gameCount;
	}
}

async function quickRemoveGame(gameId: number, userId: number) {
	const returnValues = await dboAction('quickRemoveGame', 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
		.payload({
			gameId: gameId,
			userId: userId,
		})
		.dispatch() as ReturnValues;

	// Remove game from profile list and update the owned games count
	document.getElementById('gameBox' + gameId)?.remove();
	updateGameCount(returnValues.gameCount);

	showNotification();
}

function getGameDialogTitle(gameId: number): string {
	// Use the game name and release year as the dialog title: "NAME (YEAR)"
	const gameName = document.querySelector('#gameBox' + gameId + ' .gameInfo > h3')?.textContent?.trim();
	if (!gameName) {
		return '';
	}

	const releaseYear = document.querySelector('#gameBox' + gameId + ' .gameInfo > small')?.textContent?.trim();
	return releaseYear ? gameName + ' (' + releaseYear + ')' : gameName;
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
			title: getGameDialogTitle(gameId) || getPhrase('wcf.igdb_integration.dialog.game_user_edit_title')
		},
		submitActionName: 'submitGameUserEditDialog',
		successCallback(rawReturnValues) {
			const returnValues = rawReturnValues as ReturnValues;

			// The returned game count belongs to the current user, so only
			// update the counter when viewing the own profile
			if (userId === User.userId) {
				updateGameCount(returnValues.gameCount);
			}

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

	document.getElementById('gameOverlayEdit' + gameId)?.addEventListener('click', function () {
		gameUserEditDialog.open();
	});
	document.getElementById('gameOverlayQuickRemove' + gameId)?.addEventListener('click', function () {
		void quickRemoveGame(gameId, userId);
	});
}