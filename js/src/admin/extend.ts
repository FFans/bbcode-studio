import app from 'flarum/admin/app';
import LinkButton from 'flarum/common/components/LinkButton';
import Extend from 'flarum/common/extenders';
import { extend as extendComponent } from 'flarum/common/extend';
import StudioPage from './components/StudioPage';

app.initializers.add('ffans-bbcode-studio-admin-links', () => {
  extendComponent('flarum/admin/components/ExtensionPage', 'infoItems', function (this: any, items: any) {
    if (this.extension.id !== 'ffans-bbcode-studio') return;
    if (!app.data.locale.startsWith('zh')) return;

    const forumZh = this.extension.links.forumZh;

    if (!forumZh) return;

    items.add(
      'forumZh',
      m(
        LinkButton,
        {
          href: forumZh,
          icon: 'fas fa-comments',
          external: true,
          target: '_blank',
        },
        '中文社区',
      ),
      1,
    );
  });
});

export default [new Extend.Admin().page(StudioPage)];
