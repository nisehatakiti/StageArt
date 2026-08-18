# StageArt Public Visual Reference

Public UIの見た目の正本として使用するVisual Reference。

## 一文字幕ナビゲーション

- `ichimonji-curtain-default.svg` — 通常状態。全灯体消灯。
- `ichimonji-curtain-hover.svg` — 「公演」へカーソルを乗せた状態。対象灯体のみ弱く点灯。
- `ichimonji-curtain-active.svg` — 「公演」が選択中の状態。対象灯体のみ強く点灯。

## 正本ルール

1. 一文字幕は横方向に一枚の連続した幕とする。
2. 幕そのものは発光させない。
3. 灯体は幕の下側に配置する。
4. 通常状態では灯体を消灯する。
5. Hoverでは対象メニューの灯体だけ弱く点灯する。
6. Activeでは対象メニューの灯体だけ強く点灯する。
7. 灯体の光が幕全体を照らしているような表現にはしない。
8. 緞帳が下がって地明かりが漏れているように見せない。
9. メニューごとに幕を分割しない。
10. 過度なマーキー／電飾表現にはしない。

これらのSVGは実装時のVisual Referenceであり、PublicVisualDesign.mdに定義された意味・状態・制約と合わせて参照する。
