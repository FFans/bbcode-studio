import Mithril from 'mithril';
import app from 'flarum/admin/app';
import FormModal, { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type {
  MediaSourceRule,
  MediaSourceRuleType,
  MediaTestFailureReason,
  MediaTestResult,
  RuleFormData,
  RuleResource,
  RuleType,
} from '../../common/types';
import { usageAttributes, type UsageAttribute } from '../../common/bbcodeUsage';

interface RuleModalAttrs extends IFormModalAttrs {
  rule?: RuleResource;
  onSaved: () => void;
}

const DEFAULT_MEDIA_SOURCE_PATTERN = '!video\\.example\\.com/watch/(?<id>[a-zA-Z0-9_-]+)!';
const DEFAULT_MEDIA_EMBED_URL = 'https://player.example.com/embed/{id}';
const DEFAULT_MEDIA_IFRAME_ATTRIBUTES = "allowfullscreen scrolling='no'";
const DEFAULT_MEDIA_EXAMPLE = 'https://video.example.com/watch/example-id';

const emptyRule = (): RuleFormData => ({
  ruleType: 'bbcode',
  name: '',
  tag: '',
  description: '',
  usage: '[tag]{TEXT}[/tag]',
  template: '<span class="BbcodeStudio-custom"><xsl:apply-templates/></span>',
  cssDeclarations: '',
  icon: 'fas fa-code',
  buttonLabel: '',
  example: '',
  exampleAttributes: {},
  enabled: true,
  toolbarEnabled: true,
  extractPattern: '',
  sourceRules: [{ type: 'extract', pattern: DEFAULT_MEDIA_SOURCE_PATTERN }],
  captureDefaults: {},
  embedUrl: DEFAULT_MEDIA_EMBED_URL,
  iframeAttributes: DEFAULT_MEDIA_IFRAME_ATTRIBUTES,
  aspectRatio: '16 / 9',
  sortOrder: 0,
});

export default class RuleModal extends FormModal<RuleModalAttrs> {
  data!: RuleFormData;
  embedUrlInput?: HTMLTextAreaElement;
  testUrl = '';
  testLoading = false;
  testResult?: MediaTestResult;

  oninit(vnode: Mithril.Vnode<RuleModalAttrs, this>) {
    super.oninit(vnode);
    this.data = this.copyFormData(this.attrs.rule?.attributes || emptyRule());
    this.testUrl = this.data.example;
  }

  className() {
    return 'BbcodeStudioRuleModal Modal--large';
  }

  title() {
    return app.translator.trans(
      this.attrs.rule ? 'ffans-bbcode-studio.admin.modal.edit_title' : 'ffans-bbcode-studio.admin.modal.create_title',
    );
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form BbcodeStudioRuleForm">
          <div className="Form-group">
            <label>{app.translator.trans('ffans-bbcode-studio.admin.fields.rule_type_label')}</label>
            <select
              className="FormControl"
              disabled={this.attrs.rule?.attributes.builtIn}
              value={this.data.ruleType}
              onchange={(event: Event) => this.changeRuleType((event.target as HTMLSelectElement).value as RuleType)}
            >
              <option value="bbcode">
                {app.translator.trans('ffans-bbcode-studio.admin.fields.rule_type_bbcode')}
              </option>
              <option value="media">{app.translator.trans('ffans-bbcode-studio.admin.fields.rule_type_media')}</option>
            </select>
            <div className="helpText">{app.translator.trans('ffans-bbcode-studio.admin.fields.rule_type_help')}</div>
          </div>

          <div className="BbcodeStudioRuleForm-grid">
            {this.textField('name', 'name', true, this.attrs.rule?.attributes.builtIn)}
            {this.textField('tag', 'tag', true)}
            {this.textField('icon', 'icon', true)}
            {this.textField('button_label', 'buttonLabel', false, this.attrs.rule?.attributes.builtIn)}
          </div>

          {this.textareaField('description', 'description', 2)}
          {this.data.ruleType === 'bbcode' && this.textareaField('usage', 'usage', 3, true)}
          {this.data.ruleType === 'bbcode' && this.textareaField('template', 'template', 7, true)}
          {this.data.ruleType === 'bbcode' && this.textareaField('css_declarations', 'cssDeclarations', 5)}
          {this.textareaField('example', 'example', 2)}
          {this.data.ruleType === 'bbcode' && this.exampleAttributesField()}

          <div className="BbcodeStudioRuleForm-switches">
            {this.switchField('enabled', 'enabled')}
            {this.switchField('toolbar_enabled', 'toolbarEnabled')}
          </div>

          {this.data.ruleType === 'media' && (
            <>
              <div className="Form-group BbcodeStudioSourceRules">
                <div className="BbcodeStudioSourceRules-heading">
                  <span className="FieldSet-label">
                    {app.translator.trans('ffans-bbcode-studio.admin.fields.media_group')}
                  </span>
                  <Button
                    className="Button Button--small"
                    type="button"
                    icon="fas fa-plus"
                    onclick={() => this.addSourceRule()}
                  >
                    {app.translator.trans('ffans-bbcode-studio.admin.actions.add_source_rule')}
                  </Button>
                </div>
                {this.sourceRulesField()}
              </div>
              {this.embedUrlField()}
              {this.testUrlField()}
              {this.textareaField('iframe_attributes', 'iframeAttributes', 2)}
              {this.textField('aspect_ratio', 'aspectRatio')}
            </>
          )}

          <div className="Form-group">
            <label>{app.translator.trans('ffans-bbcode-studio.admin.fields.sort_order_label')}</label>
            <input
              className="FormControl"
              type="number"
              value={this.data.sortOrder}
              oninput={(event: InputEvent) => (this.data.sortOrder = Number((event.target as HTMLInputElement).value))}
            />
            <div className="helpText">{app.translator.trans('ffans-bbcode-studio.admin.fields.sort_order_help')}</div>
          </div>

          <div className="Form-group BbcodeStudioRuleForm-actions">
            <Button className="Button Button--primary" type="submit" loading={this.loading}>
              {app.translator.trans('ffans-bbcode-studio.admin.actions.save')}
            </Button>
            {this.attrs.rule?.attributes.builtIn && this.attrs.rule.attributes.defaultAttributes && (
              <Button
                className="Button"
                type="button"
                icon="fas fa-rotate-left"
                disabled={this.loading}
                onclick={() => this.resetToDefaults()}
              >
                {app.translator.trans('ffans-bbcode-studio.admin.actions.reset')}
              </Button>
            )}
          </div>
        </div>
      </div>
    );
  }

  textField(label: string, key: keyof RuleFormData, required = false, disabled = false) {
    return (
      <div className="Form-group">
        <label>{app.translator.trans(`ffans-bbcode-studio.admin.fields.${label}_label`)}</label>
        <input
          className="FormControl"
          required={required}
          disabled={disabled}
          value={String(this.data[key] ?? '')}
          oninput={(event: InputEvent) => ((this.data[key] as string) = (event.target as HTMLInputElement).value)}
        />
        <div className="helpText">{this.fieldHelp(label)}</div>
      </div>
    );
  }

  textareaField(label: string, key: keyof RuleFormData, rows: number, required = false) {
    return (
      <div className="Form-group">
        <label>{app.translator.trans(`ffans-bbcode-studio.admin.fields.${label}_label`)}</label>
        {key === 'cssDeclarations' && this.domClassField()}
        <textarea
          className="FormControl"
          required={required}
          rows={rows}
          value={String(this.data[key] ?? '')}
          oninput={(event: InputEvent) => ((this.data[key] as string) = (event.target as HTMLTextAreaElement).value)}
        />
        {key === 'cssDeclarations' && <input className="FormControl" disabled={true} value={`}`} />}
        <div className="helpText">{this.fieldHelp(label)}</div>
      </div>
    );
  }

  fieldHelp(label: string) {
    return app.translator.trans(
      `ffans-bbcode-studio.admin.fields.${label}_help`,
      label === 'template'
        ? {
            a: (
              <a
                href="https://s9etextformatter.readthedocs.io/Plugins/BBCodes/Synopsis/"
                target="_blank"
                rel="noopener noreferrer"
              />
            ),
          }
        : {},
    );
  }

  exampleAttributesField() {
    const attributes = usageAttributes(this.data.usage);

    if (attributes.length === 0) return null;

    return (
      <div className="Form-group BbcodeStudioExampleAttributes">
        <label>{app.translator.trans('ffans-bbcode-studio.admin.fields.example_attributes_label')}</label>
        <div className="BbcodeStudioExampleAttributes-list">
          {attributes.map((attribute) => this.exampleAttributeDropdown(attribute))}
        </div>
        <div className="helpText">
          {app.translator.trans('ffans-bbcode-studio.admin.fields.example_attributes_help')}
        </div>
      </div>
    );
  }

  exampleAttributeDropdown(attribute: UsageAttribute) {
    const configured = Object.prototype.hasOwnProperty.call(this.data.exampleAttributes, attribute.name);
    const value = this.data.exampleAttributes[attribute.name];
    const status = !configured
      ? app.translator.trans('ffans-bbcode-studio.admin.fields.example_attribute_omit')
      : value === null
        ? `${attribute.name}=`
        : `${attribute.name}="${value}"`;

    return (
      <Dropdown
        className={`BbcodeStudioExampleAttribute ${configured ? 'is-configured' : ''}`}
        buttonClassName="Button Button--small"
        menuClassName="BbcodeStudioExampleAttribute-menu"
        label={`[${attribute.name}]`}
        helperText={status}
        accessibleToggleLabel={app.translator.trans(
          'ffans-bbcode-studio.admin.fields.example_attribute_edit_accessible',
          { attribute: attribute.name },
        )}
        key={attribute.name}
      >
        <div className="BbcodeStudioExampleAttribute-heading">
          <strong>{attribute.name}</strong>
          <code>{attribute.type}</code>
        </div>
        {attribute.choices.length > 0
          ? attribute.choices.map((choice) => (
              <Button
                className="Dropdown-item"
                type="button"
                icon={value === choice ? 'fas fa-check' : 'fas fa-empty'}
                onclick={() => this.setExampleAttribute(attribute.name, choice)}
                key={choice}
              >
                {choice}
              </Button>
            ))
          : this.exampleAttributeTextInput(attribute, typeof value === 'string' ? value : '')}
        <Button
          className="Dropdown-item"
          type="button"
          icon={configured && value === null ? 'fas fa-check' : 'fas fa-empty'}
          onclick={() => this.setExampleAttribute(attribute.name, null)}
        >
          {app.translator.trans('ffans-bbcode-studio.admin.fields.example_attribute_empty', {
            attribute: attribute.name,
          })}
        </Button>
        <Button
          className="Dropdown-item"
          type="button"
          icon={!configured ? 'fas fa-check' : 'fas fa-empty'}
          onclick={() => this.removeExampleAttribute(attribute.name)}
        >
          {app.translator.trans('ffans-bbcode-studio.admin.fields.example_attribute_omit')}
        </Button>
      </Dropdown>
    );
  }

  exampleAttributeTextInput(attribute: UsageAttribute, value: string) {
    return (
      <div className="BbcodeStudioExampleAttribute-input" onclick={(event: MouseEvent) => event.stopPropagation()}>
        <label>{app.translator.trans('ffans-bbcode-studio.admin.fields.example_attribute_value')}</label>
        <input
          className="FormControl"
          value={value}
          placeholder={attribute.type}
          oninput={(event: InputEvent) => {
            const input = (event.target as HTMLInputElement).value;
            this.setExampleAttribute(attribute.name, input === '' ? null : input);
          }}
        />
      </div>
    );
  }

  setExampleAttribute(name: string, value: string | null) {
    this.data.exampleAttributes[name] = value;
  }

  removeExampleAttribute(name: string) {
    delete this.data.exampleAttributes[name];
  }

  sourceRulesField() {
    return (
      <div className="BbcodeStudioSourceRules-list">
        {this.data.sourceRules.map((sourceRule, index) => this.sourceRuleRow(sourceRule, index))}
      </div>
    );
  }

  sourceRuleRow(sourceRule: MediaSourceRule, index: number) {
    const hosts = this.hostsFromPattern(sourceRule.pattern);
    const captures = sourceRule.type === 'extract' ? this.captureNamesFromPattern(sourceRule.pattern) : [];

    return (
      <div className="BbcodeStudioSourceRule" key={index}>
        <div className="BbcodeStudioSourceRule-toolbar">
          <span
            className="BbcodeStudioSourceRule-number"
            aria-label={app.translator.trans('ffans-bbcode-studio.admin.fields.source_rule_number', {
              number: index + 1,
            })}
          >
            {`#${index + 1}`}
          </span>
          <select
            className="FormControl"
            value={sourceRule.type}
            onchange={(event: Event) =>
              this.changeSourceRuleType(index, (event.target as HTMLSelectElement).value as MediaSourceRuleType)
            }
          >
            <option value="extract">
              {app.translator.trans('ffans-bbcode-studio.admin.fields.source_rule_extract')}
            </option>
            <option value="redirect">
              {app.translator.trans('ffans-bbcode-studio.admin.fields.source_rule_redirect')}
            </option>
          </select>
          <Button
            className="Button Button--icon Button--danger"
            type="button"
            icon="fas fa-trash"
            aria-label={app.translator.trans('ffans-bbcode-studio.admin.actions.remove_source_rule')}
            onclick={() => this.removeSourceRule(index)}
          />
        </div>
        <input
          className="FormControl"
          required
          value={sourceRule.pattern}
          oninput={(event: InputEvent) => {
            sourceRule.pattern = (event.target as HTMLInputElement).value;
            this.testResult = undefined;
          }}
        />
        <div className="BbcodeStudioSourceRule-meta helpText">
          <span className="BbcodeStudioSourceRule-help">
            {sourceRule.type === 'extract'
              ? app.translator.trans('ffans-bbcode-studio.admin.fields.source_rule_extract_help')
              : app.translator.trans('ffans-bbcode-studio.admin.fields.source_rule_redirect_help')}
          </span>
          {hosts.length > 0 && (
            <span className="BbcodeStudioSourceRule-metaGroup">
              <span>{app.translator.trans('ffans-bbcode-studio.admin.fields.source_hosts_prefix')}</span>
              {hosts.map((host) => (
                <code key={host}>{host}</code>
              ))}
            </span>
          )}
          {captures.length > 0 && (
            <span className="BbcodeStudioSourceRule-metaGroup">
              <span>{app.translator.trans('ffans-bbcode-studio.admin.fields.capture_variables_prefix')}</span>
              {captures.map((capture) => (
                <code key={capture}>{`{${capture}}`}</code>
              ))}
            </span>
          )}
        </div>
      </div>
    );
  }

  domClassField() {
    const tag = this.data.tag.toLowerCase();
    const className = /^[a-z][a-z0-9_-]{0,31}$/.test(tag) ? `BbcodeStudio-bbcode-${tag}` : 'BbcodeStudio-bbcode-{tag}';

    return <input className="FormControl" disabled={true} value={`.${className}`} />;
  }

  embedUrlField() {
    const captures = this.captureNames();

    return (
      <div className="Form-group">
        <label>{app.translator.trans('ffans-bbcode-studio.admin.fields.embed_url_label')}</label>
        <textarea
          className="FormControl"
          required
          rows={2}
          value={this.data.embedUrl}
          oncreate={(vnode) => (this.embedUrlInput = vnode.dom as HTMLTextAreaElement)}
          onupdate={(vnode) => (this.embedUrlInput = vnode.dom as HTMLTextAreaElement)}
          oninput={(event: InputEvent) => {
            this.data.embedUrl = (event.target as HTMLTextAreaElement).value;
            this.testResult = undefined;
          }}
        />
        <div className="helpText BbcodeStudioRuleForm-captures">
          <span>{app.translator.trans('ffans-bbcode-studio.admin.fields.embed_url_help')}</span>
          {captures.length > 0
            ? captures.map((capture) => (
                <Button
                  className="Button Button--warning Button--small"
                  type="button"
                  key={capture}
                  onclick={() => this.insertCapture(capture)}
                >
                  {`{${capture}}`}
                </Button>
              ))
            : app.translator.trans('ffans-bbcode-studio.admin.fields.capture_values_empty')}
        </div>
      </div>
    );
  }

  testUrlField() {
    const result = this.testResult;

    return (
      <div className="Form-group BbcodeStudioRuleTest">
        <label>{app.translator.trans('ffans-bbcode-studio.admin.fields.test_url_label')}</label>
        <div className="BbcodeStudioRuleTest-row">
          <input
            className="FormControl"
            type="text"
            inputmode="url"
            maxlength={2000}
            spellcheck={false}
            value={this.testUrl}
            placeholder={DEFAULT_MEDIA_EXAMPLE}
            oninput={(event: InputEvent) => {
              this.testUrl = (event.target as HTMLInputElement).value;
              this.testResult = undefined;
            }}
            onkeydown={(event: KeyboardEvent) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                void this.testMediaUrl();
              }
            }}
          />
          <Button
            className="Button"
            type="button"
            icon="fas fa-flask"
            loading={this.testLoading}
            disabled={this.testLoading || this.testUrl.trim() === ''}
            onclick={() => this.testMediaUrl()}
          >
            {app.translator.trans('ffans-bbcode-studio.admin.actions.test_url')}
          </Button>
        </div>
        <div className="helpText">{app.translator.trans('ffans-bbcode-studio.admin.fields.test_url_help')}</div>
        {result && this.testResultView(result)}
      </div>
    );
  }

  testResultView(result: MediaTestResult) {
    if (!result.matched) {
      return (
        <div className="BbcodeStudioRuleTest-result is-error" role="status" aria-live="polite">
          <i className="icon fas fa-circle-xmark" aria-hidden="true" />
          <span>{this.testFailureMessage(result.reason || 'request_failed', result.ruleNumber)}</span>
        </div>
      );
    }

    return (
      <div className="BbcodeStudioRuleTest-result is-success" role="status" aria-live="polite">
        <div className="BbcodeStudioRuleTest-status">
          <i className="icon fas fa-circle-check" aria-hidden="true" />
          <strong>
            {app.translator.trans('ffans-bbcode-studio.admin.test.matched', {
              type: app.translator.trans(`ffans-bbcode-studio.admin.fields.source_rule_${result.matchType}`),
              number: result.ruleNumber,
            })}
          </strong>
        </div>
        {Object.entries(result.captures || {}).map(([name, value]) => (
          <div className="BbcodeStudioRuleTest-detail" key={name}>
            <code>{`{${name}}`}</code>
            <span>{value}</span>
          </div>
        ))}
        <div className="BbcodeStudioRuleTest-detail">
          <strong>{app.translator.trans('ffans-bbcode-studio.admin.test.embed_url')}</strong>
          <code>{result.embedUrl}</code>
        </div>
      </div>
    );
  }

  testFailureMessage(reason: MediaTestFailureReason, ruleNumber?: number) {
    return app.translator.trans(`ffans-bbcode-studio.admin.test.${reason}`, { number: ruleNumber });
  }

  async testMediaUrl() {
    if (this.testLoading || this.testUrl.trim() === '') return;

    this.testLoading = true;
    this.testResult = undefined;

    try {
      const response = await app.request<{ data: MediaTestResult }>({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/ffans-bbcode-studio/rules/test-media`,
        body: {
          data: {
            type: 'bbcode-studio-media-tests',
            attributes: {
              url: this.testUrl,
              sourceRules: this.data.sourceRules,
              captureDefaults: this.data.captureDefaults,
              embedUrl: this.data.embedUrl,
            },
          },
        },
      });

      this.testResult = response.data;
    } catch {
      this.testResult = { matched: false, reason: 'request_failed' };
    } finally {
      this.testLoading = false;
      m.redraw();
    }
  }

  detectedHosts(type: MediaSourceRuleType): string[] {
    return [
      ...new Set(
        this.data.sourceRules
          .filter((rule) => rule.type === type)
          .flatMap((rule) => this.hostsFromPattern(rule.pattern)),
      ),
    ];
  }

  hostsFromPattern(pattern: string): string[] {
    const matches = pattern.replace(/\\\./g, '.').match(/(?:[a-z0-9-]+\.)+[a-z]{2,}/gi) || [];

    return [...new Set(matches.map((host) => host.toLowerCase()))];
  }

  captureNames(): string[] {
    return [
      ...new Set(
        this.data.sourceRules
          .filter((rule) => rule.type === 'extract')
          .flatMap((rule) => this.captureNamesFromPattern(rule.pattern)),
      ),
    ];
  }

  captureNamesFromPattern(value: string): string[] {
    const names: string[] = [];
    const pattern = /\(\?[<']([a-z][a-z0-9_]*)[>']/gi;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(value)) !== null) {
      const name = match[1].toLowerCase();

      if (!names.includes(name)) names.push(name);
    }

    return names;
  }

  sourceRulesFromLegacyPattern(pattern: string): MediaSourceRule[] {
    const rules = pattern
      .split(/\r?\n/)
      .map((value) => value.trim())
      .filter(Boolean)
      .map((value) => ({ type: 'extract' as const, pattern: value }));

    return rules.length > 0 ? rules : [{ type: 'extract', pattern: DEFAULT_MEDIA_SOURCE_PATTERN }];
  }

  copyFormData(source: RuleFormData): RuleFormData {
    return {
      ruleType: source.ruleType,
      name: source.name,
      tag: source.tag,
      description: source.description,
      usage: source.usage,
      template: source.template,
      cssDeclarations: source.cssDeclarations,
      icon: source.icon,
      buttonLabel: source.buttonLabel,
      example: source.example,
      exampleAttributes: { ...(source.exampleAttributes || {}) },
      enabled: source.enabled,
      toolbarEnabled: source.toolbarEnabled,
      extractPattern: source.extractPattern,
      sourceRules: source.sourceRules?.length
        ? source.sourceRules.map((rule) => ({ ...rule }))
        : this.sourceRulesFromLegacyPattern(source.extractPattern),
      captureDefaults: { ...(source.captureDefaults || {}) },
      embedUrl: source.embedUrl,
      iframeAttributes: source.iframeAttributes,
      aspectRatio: source.aspectRatio,
      sortOrder: source.sortOrder,
    };
  }

  resetToDefaults() {
    const defaults = this.attrs.rule?.attributes.defaultAttributes;

    if (
      !defaults ||
      !confirm(
        extractText(
          app.translator.trans('ffans-bbcode-studio.admin.confirm.reset', {
            name: this.attrs.rule?.attributes.name,
          }),
        ),
      )
    ) {
      return;
    }

    this.data = this.copyFormData(defaults);
    this.testUrl = this.data.example;
    this.testResult = undefined;
    app.alerts.show({ type: 'info' }, app.translator.trans('ffans-bbcode-studio.admin.messages.defaults_restored'));
    m.redraw();
  }

  changeRuleType(type: RuleType) {
    this.data.ruleType = type;

    if (this.attrs.rule) return;

    if (type === 'media') {
      if (!this.data.example.trim()) this.data.example = DEFAULT_MEDIA_EXAMPLE;
      if (this.data.icon === 'fas fa-code') this.data.icon = 'fas fa-play';

      return;
    }

    if (this.data.example === DEFAULT_MEDIA_EXAMPLE) this.data.example = '';
    if (this.data.icon === 'fas fa-play') this.data.icon = 'fas fa-code';
  }

  addSourceRule() {
    this.data.sourceRules.push({ type: 'extract', pattern: '' });
    this.testResult = undefined;
  }

  removeSourceRule(index: number) {
    const removing = this.data.sourceRules[index];
    this.data.sourceRules.splice(index, 1);

    if (removing.type === 'extract' && !this.data.sourceRules.some((rule) => rule.type === 'extract')) {
      this.data.sourceRules.unshift({ type: 'extract', pattern: DEFAULT_MEDIA_SOURCE_PATTERN });
    }

    this.testResult = undefined;
  }

  changeSourceRuleType(index: number, type: MediaSourceRuleType) {
    this.data.sourceRules[index].type = type;
    this.testResult = undefined;
  }

  insertCapture(capture: string) {
    const token = `{${capture}}`;
    const input = this.embedUrlInput;

    if (!input) {
      this.data.embedUrl += token;
      return;
    }

    const start = input.selectionStart ?? input.value.length;
    const end = input.selectionEnd ?? start;
    input.setRangeText(token, start, end, 'end');
    this.data.embedUrl = input.value;
    input.focus();
  }

  switchField(label: string, key: 'enabled' | 'toolbarEnabled') {
    return (
      <Switch state={this.data[key]} onchange={(value: boolean) => (this.data[key] = value)}>
        {app.translator.trans(`ffans-bbcode-studio.admin.fields.${label}_label`)}
      </Switch>
    );
  }

  async onsubmit(event: SubmitEvent) {
    event.preventDefault();
    this.loading = true;

    try {
      await app.request({
        method: this.attrs.rule ? 'PATCH' : 'POST',
        url: `${app.forum.attribute('apiUrl')}/ffans-bbcode-studio/rules${this.attrs.rule ? `/${this.attrs.rule.id}` : ''}`,
        body: {
          data: {
            type: 'bbcode-studio-rules',
            ...(this.attrs.rule ? { id: this.attrs.rule.id } : {}),
            attributes: this.data,
          },
        },
      });

      app.alerts.show({ type: 'success' }, app.translator.trans('ffans-bbcode-studio.admin.messages.saved'));
      this.attrs.onSaved();
      this.hide();
    } finally {
      this.loading = false;
      m.redraw();
    }
  }
}
