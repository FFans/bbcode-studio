# FFans BBcode Studio

[![许可证](https://img.shields.io/packagist/l/ffans/bbcode-studio.svg?label=%E8%AE%B8%E5%8F%AF%E8%AF%81)](https://packagist.org/packages/ffans/bbcode-studio) [![Flarum](https://img.shields.io/badge/dynamic/json?label=Flarum&query=%24.require%5B%22flarum%2Fcore%22%5D&url=https%3A%2F%2Fraw.githubusercontent.com%2FFFans%2Fbbcode-studio%2F2.x%2Fcomposer.json)](https://docs.flarum.org/)
[![最新版本](https://img.shields.io/github/v/tag/FFans/bbcode-studio?filter=v2.*&label=%E7%89%88%E6%9C%AC)](https://github.com/FFans/bbcode-studio/releases) [![发布日期](https://img.shields.io/github/release-date/FFans/bbcode-studio?label=%E5%8F%91%E5%B8%83%E6%97%A5%E6%9C%9F)](https://github.com/FFans/bbcode-studio/releases)
[![总下载量](https://img.shields.io/packagist/dt/ffans/bbcode-studio?label=%E6%80%BB%E4%B8%8B%E8%BD%BD%E9%87%8F)](https://packagist.org/packages/ffans/bbcode-studio) [![月下载量](https://img.shields.io/packagist/dm/ffans/bbcode-studio?label=%E6%9C%88%E4%B8%8B%E8%BD%BD%E9%87%8F)](https://packagist.org/packages/ffans/bbcode-studio)

为 [Flarum](https://flarum.org/) 创建自定义 BBCode，链接识别和自动媒体嵌入规则，基于 s9e TextFormatter。

> 本扩展在开发过程中使用了 AI 辅助。自定义渲染规则可以加载第三方服务内容，启用前请检查服务提供方的隐私政策。

## 效果预览

![preview](docs/images/preview.zh.png?raw=true)

## 功能

- 内置默认不启用的 Bilibili、YouTube、Vimeo、Spotify 和网易云音乐媒体嵌入规则。
- 内置默认不启用的提示框、折叠内容、键盘按键 BBCode。
- 在管理后台创建、编辑、排序、启用、停用和删除自定义 BBCode 与媒体嵌入规则，并进行测试验证。
- 为每个 BBCode 创建编辑器按钮，以及默认的填充内容。

## 环境要求

| Flarum 版本 | 扩展版本 | 分支  |
|-------------|----------|-------|
| 2.x         | `2.x`    | `2.x` |

## 安装

通过 Composer 安装：

```sh
composer require ffans/bbcode-studio
php flarum cache:clear
```

在 Flarum 管理后台启用本扩展。

## 更新

```sh
composer update ffans/bbcode-studio
php flarum migrate
php flarum cache:clear
```

## 使用说明

- 自定义 BBCode
    - 自定义 BBCode 规则需定义 s9e TextFormatter 用法和渲染模板，保存前会进行编译与模板检查。
    - 自定义 BBCode 根元素带有 `BbcodeStudio-bbcode` 和 `BbcodeStudio-bbcode-{tag}`，便于自定义样式。
    - 自定义 BBCode 可以配置默认填充的标签属性。
- 媒体嵌入
    - 自动识别 URL 并根据规则转换为 iframe 或其他媒体内容。
    - 媒体嵌入需定义基于 PHP PCRE 正则的 URL 标识提取规则、播放器尺寸。
    - 媒体根元素带有 `BbcodeStudio-media` 和 `BbcodeStudio-media-{tag}`，便于自定义样式。
    - 识别规则直接从链接提取平台资源标识，因此无法支持 `b23.tv` 这种不能直接对应上视频的短链接。
    - 媒体嵌入启用后，即可自动识别帖子中的链接，并转为响应式播放器。你也可以额外启用 BBCode 用法。
    - 可为媒体规则配置 iframe 属性：`allowfullscreen` 支持无值或带值写法，`allow`、`referrerpolicy` 和 `scrolling` 使用带值写法。

## 翻译

内置简体中文。

帮助翻译其他语言，请前往 [Weblate](https://weblate.rob006.net/projects/flarum2/ffans-bbcode-studio/)。

## 相关链接

- [GitHub](https://github.com/FFans/bbcode-studio)
- [Packagist](https://packagist.org/packages/ffans/bbcode-studio)
- [英文社区](https://discuss.flarum.org/d/39753)
- [中文社区](https://discuss.flarum.org.cn/d/16554)

## 许可证

[MIT](https://github.com/FFans/bbcode-studio/blob/2.x/LICENSE)
