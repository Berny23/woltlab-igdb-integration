{capture assign='contentTitle'}{lang}wcf.IgdbIntegration.page.game_list_title{/lang} <span
    class="badge">{#$items}</span>{/capture}

{capture assign='headContent'}
    {if $pageNo < $pages}
        <link rel="next" href="{link controller='IgdbIntegrationGameList'}pageNo={@$pageNo+1}{/link}">
    {/if}
    {if $pageNo > 1}
        <link rel="prev" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 2}pageNo={@$pageNo-1}{/if}{/link}">
    {/if}
    <link rel="canonical" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 1}pageNo={@$pageNo}{/if}{/link}">
{/capture}

{capture assign='sidebarRight'}
    <section id="messageBox" class="box{if $showIgdbError} error">
            {lang}wcf.IgdbIntegration.page.game_list_igdb_error{/lang}{else} info"
    >{lang}wcf.IgdbIntegration.page.game_list_info{/lang}{/if}</section>
<section class="box">
    <form id="gameSortForm" method="post" action="{link controller='IgdbIntegrationGameList'}{/link}">
        <h2 class="boxTitle">{lang}wcf.global.search{/lang}</h2>

        <div class="boxContent">
            <dl>
                <dt>{lang}wcf.global.name{/lang}</dt>
                <dd>
                    <input type="text" id="searchField" name="searchField" value="{$searchField}">
                    {event name='searchField'}
                    </select>
                </dd>
                <dt>{lang}wcf.global.sorting{/lang}</dt>
                <dd>
                    <select id="sortField" name="sortField">
                        <option value="displayName" {if $sortField == 'displayName'} selected{/if}>
                            {lang}wcf.IgdbIntegration.game.name{/lang}</option>
                        <option value="firstReleaseDateYear" {if $sortField == 'firstReleaseDateYear'} selected{/if}>
                            {lang}wcf.IgdbIntegration.game.year{/lang}</option>
                        <option value="playerCount" {if $sortField == 'playerCount'} selected{/if}>
                            {lang}wcf.IgdbIntegration.game.players{/lang}</option>
                        <option value="averageRating" {if $sortField == 'averageRating'} selected{/if}>
                            {lang}wcf.form.field.rating{/lang}</option>
                        {event name='sortField'}
                    </select>
                    <select name="sortOrder">
                        <option value="ASC" {if $sortOrder == 'ASC'} selected{/if}>
                            {lang}wcf.global.sortOrder.ascending{/lang}</option>
                        <option value="DESC" {if $sortOrder == 'DESC'} selected{/if}>
                            {lang}wcf.global.sortOrder.descending{/lang}</option>
                    </select>
                </dd>
            </dl>

            <div class="formSubmit">
                <input type="submit" value="{lang}wcf.global.button.submit{/lang}" accesskey="s">
            </div>
        </div>
    </form>
</section>
<div class="box info">{lang}wcf.IgdbIntegration.page.copyright_info{/lang}</div>

{/capture}

{include file='header'}

{hascontent}
<div class="paginationTop">
    {content}
    {pages print=true assign=pagesLinks controller='IgdbIntegrationGameList' link="pageNo=%d&searchField=$searchField&sortField=$sortField&sortOrder=$sortOrder"}
    {/content}
</div>
{/hascontent}

{if $items}
<div class="section igdbIntegrationGameListContainer">
    {foreach from=$objects item=game}
    <div class="gameBox" id="gameBox{$game->gameId}">
        <div class="gameCover" style="background-image: url({$game->coverImageUrl});">
            <ul class="gameOverlay pointer" id="gameOverlay{$game->gameId}">
                <span class="icon icon64 pointer fa-plus"></span>
            </ul>
        </div>
        <div class="gameInfo">
            <h3>{$game->displayName}</h3>
            <small>{if $game->firstReleaseDateYear != 0}{$game->firstReleaseDateYear}{/if}</small>
            <div class="gameUserInfo">
                <p class="gameAverageRating">
                    {section name=ratingStars loop=$game->averageRating}
                    <span class="icon icon16 fa-star orange"></span>
                    {/section}
                </p>
                <p class="gamePlayerCount pointer{if $game->isOwned == 1} isOwned{/if}"
                    id="gamePlayerCount{$game->gameId}" {if $game->playerCount <= 0} style="display: none;" {/if}>
                    <span class="icon fa-user"></span> {$game->playerCount}
                </p>
            </div>
        </div>
    </div>
    <script>
        require(['WoltLabSuite/Core/Form/Builder/Dialog'], function(FormBuilderDialog) {
        var dialog = new FormBuilderDialog(
            'gameUserEditDialog{$game->gameId}',
            'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
            'getGameUserEditDialog', {
                destroyOnClose: true,
                actionParameters: {
                    gameId: {$game->gameId},
                },
                dialog: {
                    title: '{lang}wcf.IgdbIntegration.dialog.game_user_edit_title{/lang}'
                },
                submitActionName: 'submitGameUserEditDialog',
                successCallback(returnValues) {
                    // Insert returned values into page

                    var html = '<p class="gameAverageRating">';

                    for (let i = 0; i < returnValues.averageRating; i++) {
                        html += '<span class="icon icon16 fa-star orange"></span>';
                    }

                    html += '</p><p class="gamePlayerCount pointer';
                    if (returnValues.isOwned) {
                        html += ' isOwned';
                    }
                    html += '" id="gamePlayerCount' + returnValues.gameId + '"';
                    if (returnValues.playerCount <= 0) {
                        html += ' style="display: none;"';
                    }
                    html += '><span class="icon fa-user"></span> ' + returnValues.playerCount +
                        '</p>';

                    document.querySelector('#gameBox' + returnValues.gameId + ' .gameUserInfo')
                        .innerHTML = html;
                }
            }
        );

        document.getElementById('gameOverlay{$game->gameId}').addEventListener('click', function() {
        dialog.open();
        });
        });
    </script>
    <script>
        require(['WoltLabSuite/Core/Form/Builder/Dialog'], function(FormBuilderDialog) {
        var dialog = new FormBuilderDialog(
            'gamePlayerListDialog{$game->gameId}',
            'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
            'getGamePlayerListDialog', {
                destroyOnClose: true,
                actionParameters: {
                    gameId: {$game->gameId},
                },
                dialog: {
                    title: '{lang}wcf.IgdbIntegration.dialog.game_player_list_title{/lang}'
                }
            }
        );

        document.getElementById('gamePlayerCount{$game->gameId}').addEventListener('click', function() {
        dialog.open();
        });
        });
    </script>
    {/foreach}
</div>
{else}
<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

<footer class="contentFooter">
    {hascontent}
    <div class="paginationBottom">
        {content}{@$pagesLinks}{/content}
    </div>
    {/hascontent}

    {hascontent}
    <nav class="contentFooterNavigation">
                <ul>{content}{event name='contentFooterNavigation'}{/content}</ul>
            </nav>
            {/hascontent}
        </footer>
        <style>
            .igdbIntegrationGameListContainer {
                display: flex;
                flex-wrap: wrap;
            }

            .igdbIntegrationGameListContainer .gameBox {
                display: flex;
                flex-direction: column;
                min-height: 18rem;
                width: 132px;
                margin: 0.25rem !important;
                border: 1px solid #ecf1f7;
                background-color: #FAFAFA;
                border-radius: 0.25rem;
            }

            .igdbIntegrationGameListContainer .gameBox .gameCover {
                height: 185px;
                border-top-left-radius: 0.25rem;
                border-top-right-radius: 0.25rem;
                background-repeat: no-repeat;
                background-position: center;
            }

            .igdbIntegrationGameListContainer .gameBox .gameCover .gameOverlay {
                display: flex;
                justify-content: center;
                height: 100%;
                align-items: center;
                background: rgba(0, 0, 0, 0.5);
                border-top-left-radius: 0.25rem;
                border-top-right-radius: 0.25rem;
                opacity: 0;
                transition: 0.2s;
            }

            .igdbIntegrationGameListContainer .gameBox .gameCover:hover .gameOverlay {
                opacity: 1;
            }

            .igdbIntegrationGameListContainer .gameBox .gameCover .gameOverlay .icon {
                color: #F5F5F5;
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo {
                display: flex;
                flex-direction: column;
                flex: 1;
                text-align: center;
                padding-top: 0.5rem;
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo>h3 {
                padding-left: 0.25rem;
                padding-right: 0.25rem;
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo {
                margin-top: auto;
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo .gamePlayerCount {
                padding: 0.25rem;
                padding-right: 0.5rem;
                text-align: end;
                transition: filter 0.2s;
                background-color: #B0BEC5;
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo .gamePlayerCount:hover {
                filter: brightness(0.5);
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo .gamePlayerCount.isOwned {
                background-color: #A5D6A7;
            }

            .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo .gamePlayerCount>.icon {
                padding-left: 0.25rem;
                float: left;
            }
        </style>

        {include file='footer'}