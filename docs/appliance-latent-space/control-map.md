# Control map

Source of truth: `assets/data/appliance-latent-space/control-map.json`.

The ids are semantic. They describe musical behaviour, not toaster parts. The appliance has not been sourced yet, and when it is, its dials and switches will bind to these ids rather than replace them.

## The vocabulary

| id | kind | group | key | MIDI | what it does |
| --- | --- | --- | --- | --- | --- |
| `browning` | continuous | tone | `b` | CC 21 | how hard the signal is cooked |
| `destruction` | continuous | tone | `d` | CC 22 | controlled damage |
| `latent_x` | continuous | latent | `x` | CC 23 | first axis of the space |
| `latent_y` | continuous | latent | `y` | CC 24 | second axis |
| `neural_mix` | continuous | latent | `n` | CC 25 | dry to wet |
| `memory` | continuous | time | `m` | CC 26 | how much of the last thing survives |
| `freeze` | toggle | time | `f` | note 38 | hold the space still |
| `plunge` | momentary | gesture | space | note 36 | push the lever |
| `capture` | momentary | gesture | `c` | note 37 | keep the current position |
| `kill` | momentary | gesture | `k` | note 39 | everything stops |

All MIDI bindings are on channel 1. `neural_mix` is named for where it is going; nothing neural is behind it yet.

## Schema

Top level:

| field | type | notes |
| --- | --- | --- |
| `schemaVersion` | string | `0.1.0` |
| `instrumentId` | string | lowercase slug |
| `instrumentName` | string | display name |
| `groups[]` | array | declares the group ids controls may use |
| `controls[]` | array | the vocabulary |

Each control:

| field | type | required | notes |
| --- | --- | --- | --- |
| `id` | string | yes | lowercase, snake_case, unique |
| `kind` | string | yes | `continuous`, `momentary` or `toggle` |
| `label` | string | no | defaults to `id` |
| `description` | string | no | short, lowercase |
| `group` | string | no | must match a declared group or it is dropped |
| `range` | `[min, max]` | no | continuous only, defaults to `[0, 1]` |
| `default` | number | no | clamped into range |
| `keyboard` | object | no | `{ key, step }`, `step` applies to continuous only |
| `midi` | object | no | `{ type, channel, number }`, type is `cc` or `note` |
| `hardware` | object | no | `{ source, notes }`, unset until the appliance exists |

## Value rules

Continuous controls clamp to their range. Momentary and toggle controls are `0` or `1`; anything `>= 0.5` reads as `1`.

## Binding uniqueness

One keyboard key and one MIDI binding per control, and no two controls may claim the same key or the same `type:channel:number`. `normalizeControlMap` enforces this by letting the first claim win and stripping the binding from later duplicates. The tests assert that the shipped map never needs that rescue.

## Adding a control

1. Add the entry to `control-map.json`. Pick an unused key and an unused CC or note number.
2. Add a row to the table above. A test asserts every id in the JSON is documented here.
3. Run `node --test __tests__/appliance-latent-space.test.js`.
4. Once the page exists, add the matching `data-control` element to the template. A parity test will require it in both directions.
