// PR #7 review (R7): Expo's ambient types (expo/types - declares `*.css`
// for src/constants/theme.ts's `import '@/global.css'`, among others).
// The usual source is expo-env.d.ts, which `expo start` generates and
// .gitignore excludes, so a fresh checkout (and CI) ran `tsc --noEmit`
// without it. Referencing the same types here keeps the type check
// independent of whether the dev server has been started.
/// <reference types="expo/types" />
