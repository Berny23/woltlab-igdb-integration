/**
 * Provides the script features shared between the game list and the user
 * profile game list.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog
 */

export function getGameDialogTitle(gameId: number): string {
	// Use the game name and release year as the dialog title: "NAME (YEAR)"
	const gameName = document.querySelector('#gameBox' + gameId + ' .gameInfo > h3')?.textContent?.trim();
	if (!gameName) {
		return '';
	}

	const releaseYear = document.querySelector('#gameBox' + gameId + ' .gameInfo > small')?.textContent?.trim();
	return releaseYear ? gameName + ' (' + releaseYear + ')' : gameName;
}

export function initGameUserEditDialogEvents(content: HTMLElement) {
	const ownedYes = content.querySelector('#isOwned') as HTMLInputElement | null;
	const ownedNo = content.querySelector('#isOwned_no') as HTMLInputElement | null;
	const ratingContainer = content.querySelector('#ratingContainer');
	if (ownedYes === null || ownedNo === null || ratingContainer === null || content.dataset.igdbEventsBound === '1') {
		return;
	}
	content.dataset.igdbEventsBound = '1';

	// Enable the owned toggle when a rating is selected
	ratingContainer.querySelectorAll('.ratingList > li:not(.ratingMetaButton)').forEach((listItem) => {
		listItem.addEventListener('click', function () {
			ownedYes.checked = true;
		});
	});

	// Reset the rating when the owned toggle is turned off
	ownedNo.addEventListener('change', function () {
		if (!ownedNo.checked) {
			return;
		}

		const ratingInput = content.querySelector('#rating') as HTMLInputElement | null;
		if (ratingInput !== null) {
			ratingInput.value = '0';
		}
		ratingContainer.querySelectorAll('.ratingList > li:not(.ratingMetaButton)').forEach((listItem) => {
			listItem.querySelector('fa-icon')?.setIcon('star', false);
		});
	});
}
