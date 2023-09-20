/**
 * Provides the script features for the game list page.
 *
 * @author		Berny23
 * @copyright	2023 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameList
 */

import { dialogFactory } from "WoltLabSuite/Core/Component/Dialog";
import { show as showNotification } from "WoltLabSuite/Core/Ui/Notification";

interface Response {
	gameId: number;
	playerCount: number;
	averageRating: number;
	isOwned: boolean;
}

async function showGameUserEditDialog(url: string): Promise<void> {
	// Call dialog form 
	const { ok, result } = await dialogFactory().usingFormBuilder().fromEndpoint<Response>(url);

	if (ok) {
		// Insert returned values into page

		var ratingElement = document.querySelector('#gameBox' + result.gameId + ' .gameAverageRating');
		var playersElement = document.getElementById('gamePlayerCount' + result.gameId);

		if (ratingElement !== null && playersElement !== null) {
			ratingElement.innerHTML = '';
			playersElement.innerHTML = '';
			playersElement.style.display = result.playerCount <= 0 ? 'none' : '';
			// Add user icon
			const userIcon = document.createElement('fa-icon');
			userIcon.size = 16;
			userIcon.setIcon('user', true);
			playersElement.appendChild(userIcon);
			playersElement.innerHTML += ' ' + result.playerCount;

			for (let i = 0; i < result.averageRating; i++) {
				// Add star icon
				const starIcon = document.createElement('fa-icon');
				starIcon.size = 16;
				starIcon.setIcon('star', true);
				ratingElement.appendChild(starIcon);
			}

			if (result.isOwned) {
				playersElement.classList.add('isOwned');
			} else {
				playersElement.classList.remove('isOwned');
			}
		}

		showNotification();
	}
}

async function showGamePlayerListDialog(url: string): Promise<void> {
	const { ok, result } = await dialogFactory().usingFormBuilder().fromEndpoint<Response>(url);

	if (ok) {
		showNotification();
	}
}

export function init(gameId: number, gameOverlayElement: HTMLElement, gamePlayerCountElement: HTMLElement) {
	gameOverlayElement.addEventListener('click', function () {
		void showGameUserEditDialog(gameOverlayElement.dataset.url!);
	});
	gamePlayerCountElement.addEventListener('click', function () {
		void showGamePlayerListDialog(gamePlayerCountElement.dataset.url!);
	});
}