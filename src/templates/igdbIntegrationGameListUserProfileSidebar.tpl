{if !$igdbGameListShowFilter|empty}
	<section class="box" id="igdbIntegrationGameListFilterBox"{if $__wcf->getUserProfileMenu()->getActiveMenuItem($user->userID)->getIdentifier() != 'igdb_integration_game_list'} hidden{/if}>
		<form method="post" action="{$igdbGameListFormUrl}">
			<h2 class="boxTitle">{lang}wcf.global.filter{/lang}</h2>

			<div class="boxContent">
				<dl>
					<dt>{lang}wcf.global.title{/lang}</dt>
					<dd>
						<input type="text" id="igdbGameListSearchField" name="gameSearch" value="{$igdbGameListFilter['search']}">
						{event name='searchField'}
					</dd>
					<dt>{lang}wcf.igdb_integration.game.platforms{/lang}</dt>
					<dd>
						<select id="igdbGameListPlatformFilter" name="gamePlatforms[]" multiple size="6">
							{foreach from=$igdbGameListAvailablePlatforms item=platform}
								<option value="{$platform}"{if $platform|in_array:$igdbGameListFilter['platforms']} selected{/if}>{$platform}</option>
							{/foreach}
						</select>
						{event name='platformFilter'}
					</dd>
					<dt>{lang}wcf.global.sorting{/lang}</dt>
					<dd>
						<select id="igdbGameListSortField" name="gameSortField">
							<option value="ownRating" {if $igdbGameListFilter['sortField'] == 'ownRating'} selected{/if}>
								{lang}wcf.form.field.rating{/lang}</option>
							<option value="displayName" {if $igdbGameListFilter['sortField'] == 'displayName'} selected{/if}>
								{lang}wcf.igdb_integration.game.name{/lang}</option>
							<option value="releaseYear" {if $igdbGameListFilter['sortField'] == 'releaseYear'} selected{/if}>
								{lang}wcf.igdb_integration.game.year{/lang}</option>
							<option value="playerCount" {if $igdbGameListFilter['sortField'] == 'playerCount'} selected{/if}>
								{lang}wcf.igdb_integration.game.players{/lang}</option>
							<option value="lastInteractionTime" {if $igdbGameListFilter['sortField'] == 'lastInteractionTime'} selected{/if}>
								{lang}wcf.igdb_integration.game.last_interaction{/lang}</option>
							{event name='sortField'}
						</select>
						<select name="gameSortOrder">
							<option value="ASC" {if $igdbGameListFilter['sortOrder'] == 'ASC'} selected{/if}>
								{lang}wcf.global.sortOrder.ascending{/lang}</option>
							<option value="DESC" {if $igdbGameListFilter['sortOrder'] == 'DESC'} selected{/if}>
								{lang}wcf.global.sortOrder.descending{/lang}</option>
						</select>
					</dd>
				</dl>

				<div class="formSubmit">
					<input type="hidden" name="igdbGameListFilter" value="1">
					<input type="submit" value="{lang}wcf.global.button.submit{/lang}">
					<a href="{$igdbGameListResetUrl}" class="button">{lang}wcf.global.button.reset{/lang}</a>
				</div>
			</div>
		</form>
	</section>
	<script data-relocate="true">
		(function () {
			// Move the filter box to the very top of the sidebar, the core
			// sidebar template only provides an insert position at the end of
			// the box list
			var filterBox = document.getElementById('igdbIntegrationGameListFilterBox');
			var boxContainer = filterBox.closest('.boxContainer');
			if (boxContainer !== null) {
				boxContainer.prepend(filterBox);
			}
		})();

		require(['WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile'], (ControllerIgdbIntegrationGameListUserProfile) => {
			ControllerIgdbIntegrationGameListUserProfile.watchFilterBoxVisibility();
		});
	</script>
{/if}
