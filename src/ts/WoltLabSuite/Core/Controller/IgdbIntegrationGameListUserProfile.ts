/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { confirmGameRemoval, getGameDialogTitle, initGameUserEditDialogEvents } from "WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog";
import { showGamePlayerListDialog } from "WoltLabSuite/Core/Controller/IgdbIntegrationGameList";
import * as EventHandler from "WoltLabSuite/Core/Event/Handler";
import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { show as showNotification } from "WoltLabSuite/Core/Ui/Notification";
import User from "WoltLabSuite/Core/User";

interface ReturnValues {
	gameId: number;
	playerCount: number;
	ownRating: number;
	isOwned: boolean;
	gameCount: number;
}

const FILTER_PARAMETER_REGEX = /[?&](?:gameSearch|gameSortField|gameSortOrder|gamePlatforms(?:\[\]|%5B%5D)|pageNo)=[^&#]*/g;
const GAME_LIST_TAB_NAME = 'igdb_integration_game_list';

let tabWatcherActive = false;
let removedFilterParameters = '';

/**
 * Removes the game list filter parameters from the address bar when another
 * profile tab is selected, so the filter is not reapplied on reload there,
 * and restores them when the games tab is selected again.
 */
export function watchTabSelection() {
	if (tabWatcherActive) {
		return;
	}
	tabWatcherActive = true;

	EventHandler.add('com.woltlab.wcf.simpleTabMenu_profileContent', 'select', (data: { activeName: string }) => {
		// The profile URL is not a regular query string without URL rewriting
		// (e.g. "index.php?user/1-example/&gameSearch="), so the parameters
		// are handled on the raw URL instead of using URLSearchParams
		const hash = window.location.hash;
		let url = window.location.href.replace(/#.*$/, '');

		if (data.activeName === GAME_LIST_TAB_NAME) {
			// Restore the parameters that were removed when the tab was left,
			// the displayed list still matches them
			if (removedFilterParameters !== '') {
				url += (url.includes('?') ? '&' : '?') + removedFilterParameters;
				removedFilterParameters = '';
				window.history.replaceState(undefined, '', url + hash);
			}

			return;
		}

		const parameters = url.match(FILTER_PARAMETER_REGEX);
		if (parameters === null) {
			return;
		}
		removedFilterParameters = parameters.map((parameter) => parameter.substring(1)).join('&');

		url = url.replace(FILTER_PARAMETER_REGEX, '');
		if (!url.includes('?') && url.includes('&')) {
			// The removed part contained the "?" of the remaining parameters
			url = url.replace('&', '?');
		}
		window.history.replaceState(undefined, '', url + hash);
	});
}

/**
 * Only shows the sidebar filter box while the games tab is selected.
 */
export function watchFilterBoxVisibility() {
	const filterBox = document.getElementById('igdbIntegrationGameListFilterBox');
	if (filterBox === null) {
		return;
	}

	// The server only preselects the games tab when filter parameters are
	// present; a tab named in the URL hash is activated by the tab menu script
	// alone, so the initial visibility has to be corrected here
	const hashMatch = /^#+([^/]+)/.exec(window.location.hash);
	if (hashMatch !== null) {
		const tabContent = document.getElementById(hashMatch[1]);
		if (tabContent !== null && tabContent.parentElement?.id === 'profileContent') {
			filterBox.hidden = hashMatch[1] !== GAME_LIST_TAB_NAME;
		}
	}

	EventHandler.add('com.woltlab.wcf.simpleTabMenu_profileContent', 'select', (data: { activeName: string }) => {
		filterBox.hidden = data.activeName !== GAME_LIST_TAB_NAME;
	});
}

function updateGameCount(gameCount: number) {
	const countElement = document.getElementById('igdbIntegrationGameCount');
	if (countElement !== null) {
		countElement.textContent = getPhrase('wcf.user.option.igdb_integration_game_count') + ': ' + gameCount;
	}
}

async function quickRemoveGame(gameId: number, userId: number) {
	if (!(await confirmGameRemoval(getGameDialogTitle(gameId)))) {
		return;
	}

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
			title: getGameDialogTitle(gameId) || getPhrase('wcf.igdb_integration.dialog.game_user_edit_title'),
			onShow: (content) => initGameUserEditDialogEvents(content)
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
					playersElement.innerHTML = '';
					playersElement.style.display = returnValues.playerCount <= 0 ? 'none' : '';
					const userIcon = document.createElement('fa-icon');
					userIcon.size = 16;
					userIcon.setIcon('user', true);
					playersElement.appendChild(userIcon);
					playersElement.innerHTML += ' ' + returnValues.playerCount;

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
	document.getElementById('gamePlayerCount' + gameId)?.addEventListener('click', function () {
		void showGamePlayerListDialog(gameId);
	});
}