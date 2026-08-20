/**
 * Provides the buttons on game boxes embedded into messages via the
 * [igdbgame] bbcode: opening the game details dialog and quickly adding or
 * removing the game.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameEmbed
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { confirmGameRemoval, initGameUserEditDialogEvents } from "WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog";
import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { show as showNotification } from "WoltLabSuite/Core/Ui/Notification";

interface Response {
	gameId: number;
	isOwned: boolean;
	playerCount: number;
	currentUserRating?: number;
}

let initialized = false;

function updateEmbeds(response: Response) {
	// A game can be embedded multiple times on the same page, e.g. in
	// several posts of a thread, so update all of its embed boxes
	document.querySelectorAll<HTMLElement>('.igdbIntegrationGameEmbed[data-game-id="' + response.gameId + '"]').forEach((embed) => {
		const quickAddButton = embed.querySelector<HTMLElement>('.igdbIntegrationGameEmbedQuickAdd');
		if (quickAddButton !== null) {
			quickAddButton.dataset.isOwned = response.isOwned ? '1' : '0';
			quickAddButton.title = getPhrase(response.isOwned
				? 'wcf.igdb_integration.page.game_quick_remove'
				: 'wcf.igdb_integration.page.game_quick_add');
			quickAddButton.querySelector('fa-icon')?.setIcon(response.isOwned ? 'minus' : 'plus', true);
		}

		const playerCountElement = embed.querySelector<HTMLElement>('.igdbIntegrationGameEmbedPlayerCount');
		if (playerCountElement !== null && response.playerCount >= 0) {
			playerCountElement.textContent = String(response.playerCount);
		}

		// The author badge and rating reflect the message author; they can
		// only change through this page if the viewer is the author
		if (embed.dataset.authorIsCurrentUser === '1') {
			embed.classList.toggle('igdbIntegrationGameEmbedAuthorOwns', response.isOwned);
			updateAuthorRating(embed, response);
		}
	});
}

function updateAuthorRating(embed: HTMLElement, response: Response) {
	const rating = response.isOwned ? (response.currentUserRating ?? 0) : 0;

	let ratingElement = embed.querySelector<HTMLElement>('.igdbIntegrationGameEmbedAuthorRating');
	if (rating <= 0) {
		ratingElement?.remove();
		return;
	}

	if (ratingElement === null) {
		ratingElement = document.createElement('p');
		ratingElement.className = 'igdbIntegrationGameEmbedAuthorRating orange';
		ratingElement.title = getPhrase('wcf.igdb_integration.bbcode.author_rating');
		embed.querySelector('.igdbIntegrationGameEmbedButtons')?.before(ratingElement);
	}

	ratingElement.innerHTML = '';
	for (let i = 0; i < rating; i++) {
		// Add star icon
		const starIcon = document.createElement('fa-icon');
		starIcon.size = 16;
		starIcon.setIcon('star', true);
		ratingElement.appendChild(starIcon);
	}
}

function showGamePlayerListDialog(embed: HTMLElement, gameId: number) {
	let form = new FormBuilderDialog(
		'gamePlayerListDialog' + gameId,
		'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
		'getGamePlayerListDialog', {
		destroyOnClose: true,
		actionParameters: {
			gameId: gameId,
		},
		dialog: {
			title: embed.dataset.gameTitle || getPhrase('wcf.igdb_integration.dialog.game_player_list_title')
		}
	});

	form.open();
}

function showGameUserEditDialog(embed: HTMLElement, gameId: number) {
	let form = new FormBuilderDialog(
		'gameUserEditDialog' + gameId,
		'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
		'getGameUserEditDialog', {
		destroyOnClose: true,
		actionParameters: {
			gameId: gameId,
		},
		dialog: {
			title: embed.dataset.gameTitle || getPhrase('wcf.igdb_integration.dialog.game_user_edit_title'),
			onShow: (content) => initGameUserEditDialogEvents(content)
		},
		submitActionName: 'submitGameUserEditDialog',
		successCallback(returnValues) {
			updateEmbeds(returnValues as Response);
			showNotification();
		}
	});

	form.open();
}

async function quickToggleGame(button: HTMLElement, embed: HTMLElement, gameId: number) {
	const isRemoval = button.dataset.isOwned === '1';
	if (isRemoval && !(await confirmGameRemoval(embed.dataset.gameTitle || ''))) {
		return;
	}

	const actionName = isRemoval ? 'quickRemoveGame' : 'quickAddGame';
	const returnValues = await dboAction(actionName, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
		.payload({
			gameId: gameId,
		})
		.dispatch();

	updateEmbeds(returnValues as Response);
	showNotification();
}

export function setup() {
	// The bbcode can occur any number of times on a page and its output is
	// also inserted dynamically, e.g. by the message preview, so the buttons
	// are handled by a single delegated listener
	if (initialized) {
		return;
	}
	initialized = true;

	document.addEventListener('click', (event) => {
		const target = event.target as HTMLElement;

		const editButton = target.closest<HTMLElement>('.igdbIntegrationGameEmbedEdit');
		if (editButton !== null) {
			const embed = editButton.closest<HTMLElement>('.igdbIntegrationGameEmbed');
			if (embed !== null) {
				showGameUserEditDialog(embed, parseInt(embed.dataset.gameId || '0', 10));
			}
			return;
		}

		const quickAddButton = target.closest<HTMLElement>('.igdbIntegrationGameEmbedQuickAdd');
		if (quickAddButton !== null) {
			const embed = quickAddButton.closest<HTMLElement>('.igdbIntegrationGameEmbed');
			if (embed !== null) {
				void quickToggleGame(quickAddButton, embed, parseInt(embed.dataset.gameId || '0', 10));
			}
			return;
		}

		const playerListButton = target.closest<HTMLElement>('.igdbIntegrationGameEmbedPlayerList');
		if (playerListButton !== null) {
			const embed = playerListButton.closest<HTMLElement>('.igdbIntegrationGameEmbed');
			if (embed !== null) {
				showGamePlayerListDialog(embed, parseInt(embed.dataset.gameId || '0', 10));
			}
		}
	});
}
