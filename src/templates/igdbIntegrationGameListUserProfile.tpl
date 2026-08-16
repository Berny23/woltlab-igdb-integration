{if $userGames}
	<div class="status info" id="igdbIntegrationGameCount">{lang}wcf.user.option.igdb_integration_game_count{/lang}: {$gameCount}</div>
	<br />
	{if $gameListPages > 1}
		<div class="paginationTop">
			<woltlab-core-pagination page="{$gameListPageNo}" count="{$gameListPages}" url="{$gameListBaseUrl}"></woltlab-core-pagination>
		</div>
	{/if}
	<div class="section igdbIntegrationGameListContainer">
		{foreach from=$userGames item=game}
			<div class="gameBox" id="gameBox{$game['gameId']}">
				<div class="gameCover" style="background-image: url({$game['coverImageUrl']});">
					<div class="gameOverlay" id="gameOverlay{$game['gameId']}">
						<span class="gameOverlayButton pointer" id="gameOverlayEdit{$game['gameId']}"
							title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
							{if $__wcf->user->userID && $__wcf->session->getPermission('user.igdb_integration.can_manage_own_games')}
								{icon size=48 name='pen-to-square' type='solid'}
							{else}
								{icon size=48 name='circle-info' type='solid'}
							{/if}
						</span>
						{if $__wcf->user->userID == $userId && $__wcf->session->getPermission('user.igdb_integration.can_manage_own_games')}
							<span class="gameOverlayButton pointer" id="gameOverlayQuickRemove{$game['gameId']}"
								title="{lang}wcf.igdb_integration.page.game_quick_remove{/lang}">
								{icon size=48 name='minus' type='solid'}
							</span>
						{/if}
					</div>
				</div>
				<div class="gameInfo">
					<h3>{$game['displayName']}</h3>
					<small>{$game['releaseYear']}</small>
					<div class="gameUserInfo">
						<p class="gameOwnRating orange">
							{section name=ratingStars loop=$game['ownRating']}{icon size=16 name='star' type='solid'}{/section}
						</p>
						<p class="gamePlayerCount pointer{if $game['isOwned'] == 1} isOwned{/if}"
							id="gamePlayerCount{$game['gameId']}" {if $game['playerCount'] <= 0} style="display: none;" {/if}>
							{icon size=16 name='user' type='solid'} {$game['playerCount']}
						</p>
					</div>
				</div>
			</div>
		{/foreach}
	</div>
	{if $gameListPages > 1}
		<div class="paginationBottom">
			<woltlab-core-pagination page="{$gameListPageNo}" count="{$gameListPages}" url="{$gameListBaseUrl}"></woltlab-core-pagination>
		</div>
	{/if}
{else}
	<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

{if IGDB_INTEGRATION_GENERAL_SHOW_BRANDING}
	<footer class="contentFooter igdbIntegrationFooter">
		<div class="igdbIntegrationCopyright">{lang}wcf.igdb_integration.page.copyright_info{/lang}</div>
	</footer>
{/if}

<script data-relocate="true">
	require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile'], (Language,
		ControllerIgdbIntegrationGameListUserProfile) => {
		Language.addObject({
			'wcf.igdb_integration.dialog.game_user_edit_title': '{jslang}wcf.igdb_integration.dialog.game_user_edit_title{/jslang}',
			'wcf.igdb_integration.dialog.game_player_list_title': '{jslang}wcf.igdb_integration.dialog.game_player_list_title{/jslang}',
			'wcf.user.option.igdb_integration_game_count': '{jslang}wcf.user.option.igdb_integration_game_count{/jslang}'
		});
		ControllerIgdbIntegrationGameListUserProfile.watchTabSelection();
		{foreach from=$userGames item=game}
			ControllerIgdbIntegrationGameListUserProfile.init({unsafe:$game['gameId']}, {$userId});
		{/foreach}
	});
</script>