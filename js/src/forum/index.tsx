import type Mithril from 'mithril';
import app from 'flarum/forum/app';
import { extend as extendComponent } from 'flarum/common/extend';
import type TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';
import type ItemList from 'flarum/common/utils/ItemList';
import type EditorDriverInterface from 'flarum/common/utils/EditorDriverInterface';
import type { ToolbarRule } from '../common/types';
import { openingTag } from '../common/bbcodeUsage';

export const extend: unknown[] = [];

app.initializers.add('ffans-bbcode-studio', () => {
  (extendComponent as any)(
    'flarum/common/components/TextEditor',
    'toolbarItems',
    function (this: TextEditor, items: ItemList<Mithril.Children>) {
      const rules = app.forum.attribute<ToolbarRule[]>('ffansBbcodeStudioToolbarRules') || [];

      if (rules.length === 0) return;

      rules.forEach((rule, index) => {
        const title = rule.buttonLabelTranslationKey
          ? app.translator.trans(rule.buttonLabelTranslationKey)
          : rule.buttonLabel;

        items.add(
          `ffans-bbcode-studio-${rule.id}`,
          <TextEditorButton icon={rule.icon} title={title} onclick={() => insertRule(this, rule)} />,
          20 - index, // random picked basic priority, doesn't mean anything.
        );
      });
    },
  );
});

function insertRule(editorComponent: TextEditor, rule: ToolbarRule) {
  const composer = (editorComponent.attrs as any).composer;
  const editor = composer.editor as EditorDriverInterface;
  const [start, end] = editor.getSelectionRange();
  const currentValue = String((editorComponent as any).value || '');
  const selection = currentValue.slice(start, end);
  const open = openingTag(rule.tag, rule.exampleAttributes || {});
  const close = `[/${rule.tag}]`;
  const content = selection || rule.example || '';

  editor.insertBetween(start, end, `${open.value}${content}${close}`, false);

  // if (open.emptyValueOffset !== null) {
  //   editor.moveCursorTo(start + open.emptyValueOffset);
  // } else
  if (!selection) {
    editor.moveCursorTo(start + open.value.length);
  }
}
