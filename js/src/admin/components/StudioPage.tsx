import Mithril from 'mithril';
import app from 'flarum/admin/app';
import ExtensionPage, { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Icon from 'flarum/common/components/Icon';
import extractText from 'flarum/common/utils/extractText';
import RuleModal from './RuleModal';
import type { RuleResource, RuleType } from '../../common/types';

interface RulesDocument {
  data: RuleResource[];
}

export default class StudioPage extends ExtensionPage {
  loading = true;
  rules: RuleResource[] = [];

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);
    this.load();
  }

  content() {
    return (
      <div className="BbcodeStudioPage ExtensionPage-settings">
        <div className="container">
          <div className="BbcodeStudioPage-header">
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.openEditor()}>
              {app.translator.trans('ffans-bbcode-studio.admin.actions.create')}
            </Button>
          </div>

          <div className="Alert Alert--warning BbcodeStudioPage-securityNotice">
            <Icon name="fas fa-shield-halved" />
            <span>{app.translator.trans('ffans-bbcode-studio.admin.page.security_notice')}</span>
          </div>

          {this.loading ? (
            <LoadingIndicator />
          ) : this.rules.length === 0 ? (
            <Placeholder text={app.translator.trans('ffans-bbcode-studio.admin.page.empty')} />
          ) : (
            <div className="BbcodeStudioRuleGroups">
              {this.ruleGroup('bbcode')}
              {this.ruleGroup('media')}
            </div>
          )}
        </div>
      </div>
    );
  }

  ruleGroup(type: RuleType) {
    const rules = this.rules.filter((rule) => rule.attributes.ruleType === type);
    const headingId = `BbcodeStudioRuleGroup-${type}`;

    return (
      <section className="BbcodeStudioRuleGroup" aria-labelledby={headingId}>
        <div className="BbcodeStudioRuleGroup-heading">
          <div>
            <h2 id={headingId}>
              <Icon name={type === 'bbcode' ? 'fas fa-code' : 'fas fa-photo-film'} />
              {app.translator.trans(`ffans-bbcode-studio.admin.groups.${type}_title`)}
            </h2>
            <p>{app.translator.trans(`ffans-bbcode-studio.admin.groups.${type}_description`)}</p>
          </div>
          <span
            className="Badge"
            aria-label={extractText(
              app.translator.trans('ffans-bbcode-studio.admin.groups.rule_count', { count: rules.length }),
            )}
          >
            ×{rules.length}
          </span>
        </div>

        {rules.length > 0 ? (
          <div className="BbcodeStudioRuleList">{rules.map((rule) => this.ruleCard(rule))}</div>
        ) : (
          <p className="BbcodeStudioRuleGroup-empty">
            {app.translator.trans(`ffans-bbcode-studio.admin.groups.${type}_empty`)}
          </p>
        )}
      </section>
    );
  }

  ruleCard(rule: RuleResource) {
    const attributes = rule.attributes;

    return (
      <article className={`BbcodeStudioRuleCard ${attributes.enabled ? '' : 'is-disabled'}`} key={rule.id}>
        <div className="BbcodeStudioRuleCard-icon">
          <Icon name={attributes.icon} />
        </div>
        <div className="BbcodeStudioRuleCard-main">
          <div className="BbcodeStudioRuleCard-heading">
            <h3>{attributes.name}</h3>
            <code>[{attributes.tag}]</code>
            <span className={`Badge ${attributes.enabled ? 'Badge--success' : ''}`}>
              {app.translator.trans(
                attributes.enabled
                  ? 'ffans-bbcode-studio.admin.status.enabled'
                  : 'ffans-bbcode-studio.admin.status.disabled',
              )}
            </span>
            {attributes.builtIn && (
              <span className="Badge">{app.translator.trans('ffans-bbcode-studio.admin.status.built_in')}</span>
            )}
          </div>
          {attributes.description && <p>{attributes.description}</p>}
          <div className="BbcodeStudioRuleCard-meta">
            {attributes.toolbarEnabled && (
              <span>
                <Icon name="fas fa-keyboard" /> {app.translator.trans('ffans-bbcode-studio.admin.status.toolbar')}
              </span>
            )}
            {attributes.ruleType === 'media' && (
              <span>
                <Icon name="fas fa-wand-magic-sparkles" />{' '}
                {app.translator.trans('ffans-bbcode-studio.admin.status.media_recognition')}
              </span>
            )}
          </div>
        </div>
        <div className="BbcodeStudioRuleCard-actions">
          <Button
            className="Button Button--icon"
            icon="fas fa-pen"
            aria-label={app.translator.trans('ffans-bbcode-studio.admin.actions.edit')}
            onclick={() => this.openEditor(rule)}
          />
          {!attributes.builtIn && (
            <Button
              className="Button Button--icon Button--danger"
              icon="fas fa-trash"
              aria-label={app.translator.trans('ffans-bbcode-studio.admin.actions.delete')}
              onclick={() => this.deleteRule(rule)}
            />
          )}
        </div>
      </article>
    );
  }

  async load() {
    this.loading = true;

    try {
      const response = await app.request<RulesDocument>({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/ffans-bbcode-studio/rules`,
      });
      this.rules = response.data;
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  openEditor(rule?: RuleResource) {
    app.modal.show(RuleModal, {
      rule,
      onSaved: () => this.load(),
    });
  }

  async deleteRule(rule: RuleResource) {
    if (
      !confirm(
        extractText(app.translator.trans('ffans-bbcode-studio.admin.confirm.delete', { name: rule.attributes.name })),
      )
    )
      return;

    await app.request({
      method: 'DELETE',
      url: `${app.forum.attribute('apiUrl')}/ffans-bbcode-studio/rules/${rule.id}`,
    });

    app.alerts.show({ type: 'success' }, app.translator.trans('ffans-bbcode-studio.admin.messages.deleted'));
    await this.load();
  }
}
