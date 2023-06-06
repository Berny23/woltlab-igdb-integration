{include file='header' pageTitle='wcf.IgdbIntegration.page.game_list_title'}

<header class="contentHeader">
    <div class="contentHeaderTitle">
        <h1 class="contentTitle">{lang}wcf.IgdbIntegration.page.game_list_title{/lang}</h1>
    </div>

    <nav class="contentHeaderNavigation">
        <ul>
            {*<li><a href="{link controller='IgdbIntegrationGameAdd'}{/link}" class="button">
                    <span class="icon icon16 fa-plus"></span>
                    <span>{lang}wcf.acp.menu.link.IgdbIntegration.game_add{/lang}</span>
                </a>
            </li>*}
            {event name='contentHeaderNavigation'}
        </ul>
    </nav>
</header>

{hascontent}
<div class="paginationTop">
    {content}{pages print=true assign=pagesLinks controller="IgdbIntegrationGameList" link="pageNo=%d&sortField=$sortField&sortOrder=$sortOrder"}{/content}
</div>
{/hascontent}

{if $objects|count}
    <div class="section tabularBox">
        <table class="table jsObjectActionContainer"
            data-object-action-class-name="wcf\data\IgdbIntegration\IgdbIntegrationGameAction">
            <thead>
                <tr>
                    <th class="columnID columnGameId{if $sortField == 'gameId'} active {@$sortOrder}{/if}" colspan="2"><a
                            href="{link controller='IgdbIntegrationGameList'}pageNo={@$pageNo}&sortField=gameId&sortOrder={if $sortField == 'gameId' && $sortOrder == 'ASC'}DESC{else}ASC{/if}{/link}">{lang}wcf.global.objectID{/lang}</a>
                    </th>
                    <th class="columnTitle columnName{if $sortField == 'name'} active {@$sortOrder}{/if}"><a
                            href="{link controller='IgdbIntegrationGameList'}pageNo={@$pageNo}&sortField=name&sortOrder={if $sortField == 'name' && $sortOrder == 'ASC'}DESC{else}ASC{/if}{/link}">{lang}wcf.IgdbIntegration.game.name{/lang}</a>
                    </th>
                    <th class="columnTitle columnYear{if $sortField == 'firstReleaseDateYear'} active {@$sortOrder}{/if}"><a
                            href="{link controller='IgdbIntegrationGameList'}pageNo={@$pageNo}&sortField=firstReleaseDateYear&sortOrder={if $sortField == 'firstReleaseDateYear' && $sortOrder == 'ASC'}DESC{else}ASC{/if}{/link}">{lang}wcf.IgdbIntegration.game.year{/lang}</a>
                    </th>
                    <th class="columnTitle columnPlatforms{if $sortField == 'platforms'} active {@$sortOrder}{/if}"><a
                            href="{link controller='IgdbIntegrationGameList'}pageNo={@$pageNo}&sortField=platforms&sortOrder={if $sortField == 'platforms' && $sortOrder == 'ASC'}DESC{else}ASC{/if}{/link}">{lang}wcf.IgdbIntegration.game.platforms{/lang}</a>
                    </th>

                    {event name='columnHeads'}
                </tr>
            </thead>

            <tbody class="jsReloadPageWhenEmpty">
                {foreach from=$objects item=game}
                    <tr class="jsObjectActionObject" data-object-id="{@$game->getObjectID()}">
                        <td class="columnIcon">
                            <a href="{link controller='IgdbIntegrationGameEdit' object=$game}{/link}"
                                title="{lang}wcf.global.button.edit{/lang}" class="jsTooltip">
                                <span class="icon icon16 fa-pencil"></span>
                            </a>
                            {objectAction action="delete" objectTitle=$game->displayName parametergameId=$game->gameId}

                            {event name='rowButtons'}
                        </td>
                        <td class="columnID">{#$game->gameId}</td>
                        <td class="columnTitle columnName"><a
                                href="{link controller='IgdbIntegrationGameEdit' object=$game}{/link}">{$game->displayName}</a>
                        </td>
                        <td class="columnTitle columnYear"><a
                                href="{link controller='IgdbIntegrationGameEdit' object=$game}{/link}">{$game->firstReleaseDateYear}</a>
                        </td>
                        <td class="columnTitle columnPlatforms"><a
                                href="{link controller='IgdbIntegrationGameEdit' object=$game}{/link}">{$game->platforms}</a>
                        </td>

                        {event name='columns'}
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>

    <footer class="contentFooter">
        {hascontent}
        <div class="paginationBottom">
            {content}{@$pagesLinks}{/content}
        </div>
        {/hascontent}

        <nav class="contentFooterNavigation">
            <ul>
                {*<li>
                    <a href="{link controller='IgdbIntegrationGameAdd'}{/link}" class="button">
                        <span class="icon icon16 fa-plus"></span>
                        <span>{lang}wcf.acp.menu.link.IgdbIntegration.game_add{/lang}</span>
                    </a>
                </li>*}
                {event name='contentFooterNavigation'}
            </ul>
        </nav>
    </footer>
{else}
    <p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

{include file='footer'}