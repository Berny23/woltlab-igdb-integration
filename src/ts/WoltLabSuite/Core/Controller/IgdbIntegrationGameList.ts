/**
 * Provides the script features for the game list page.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameList
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { confirmGameRemoval, getGameDialogTitle, initGameUserEditDialogEvents, updateQuickToggleButton } from "WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog";
import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { show as showNotification } from "WoltLabSuite/Core/Ui/Notification";

interface Response {
	gameId: number;
	playerCount: number;
	averageRating: number;
	isOwned: boolean;
}

function updateGameBox(response: Response) {
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

	updateQuickToggleButton(response.gameId, response.isOwned);
}

export async function showGameUserEditDialog(gameId: number) {
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
			title: getGameDialogTitle(gameId) || getPhrase('wcf.igdb_integration.dialog.game_user_edit_title'),
			onShow: (content) => initGameUserEditDialogEvents(content)
		},
		submitActionName: 'submitGameUserEditDialog',
		successCallback(returnValues) {
			updateGameBox(returnValues as Response);
			showNotification();
		}
	});

	form.open();
}

async function quickToggleGame(gameId: number) {
	const button = document.getElementById('gameOverlayQuickAdd' + gameId);
	if (button === null) {
		return;
	}

	const isRemoval = button.dataset.isOwned === '1';
	if (isRemoval && !(await confirmGameRemoval(getGameDialogTitle(gameId)))) {
		return;
	}

	const actionName = isRemoval ? 'quickRemoveGame' : 'quickAddGame';
	const returnValues = await dboAction(actionName, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
		.payload({
			gameId: gameId,
		})
		.dispatch();

	updateGameBox(returnValues as Response);
	showNotification();
}

export async function showGamePlayerListDialog(gameId: number) {
	let form = new FormBuilderDialog(
		'gamePlayerListDialog' + gameId,
		'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
		'getGamePlayerListDialog', {
		destroyOnClose: true,
		actionParameters: {
			gameId: gameId,
		},
		dialog: {
			title: getGameDialogTitle(gameId) || getPhrase('wcf.igdb_integration.dialog.game_player_list_title')
		}
	});

	form.open();
}

export function init(gameId: number) {
	document.getElementById('gameOverlayEdit' + gameId)?.addEventListener('click', function () {
		showGameUserEditDialog(gameId);
	});
	document.getElementById('gameOverlayQuickAdd' + gameId)?.addEventListener('click', function () {
		void quickToggleGame(gameId);
	});
	document.getElementById('gamePlayerCount' + gameId)?.addEventListener('click', function () {
		showGamePlayerListDialog(gameId);
	});
}