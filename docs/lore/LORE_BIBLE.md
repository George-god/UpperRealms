# UpperRealms — Lore Bible, Volume I

**Status:** Living creative reference, version 0.2 · **Scope:** Canon Checkpoint 01 and existing prologue  
**Audience:** Writers, designers and Codex working on story content  
**Spoiler policy:** This directory is developer-facing. Do not expose its contents wholesale through the player Codex.

## Purpose

UpperRealms is a cultivation MMORPG about a wanderer who awakens without memories in a world saturated with qi. The story begins locally, through human relationships, survival and learning to cultivate, while a larger danger to the Heavenly Dao emerges over time. The destination of the long-term narrative is a potential struggle to protect the Dao and existence from the Outer Gods; the cosmological foundations are approved in Checkpoint 01; the mechanism of the current crisis and ultimate resolution remain open.

This Bible records decisions without confusing a pitch with implemented content.

## Canon and confidence labels

- **Established / Canon:** Author-approved world facts (distinguish ancient history and present-day facts), or existing narrative game content. An established scene is not proof of an unseen explanation.
- **Planned future:** Approved narrative direction, not an event that has occurred at player start; implementation, pacing and precise outcomes remain adjustable.
- **In-game account:** A claim made by a character, dream, text or archivist. Such claims can be incomplete, biased, mistaken or allegorical.
- **Proposed:** A candidate for approval, never an instruction to automatically change implementation.
- **Open:** A deliberate unanswered question or contradiction needing author approval.
- **Secret:** An internal narrative question/reveal. Do not put these answers in public quest descriptions, tooltips or Codex entries.

**Source priority:** (1) Author-approved decisions; (2) existing story dialogue and scene content; (3) other player-facing game text; (4) older planning ideas. If sources conflict, flag it here rather than silently retconning either. Data from gameplay schemas is evidence of mechanics, not necessarily of a canonical historical event.

## Established opening foundation

1. The protagonist wakes in **Spirit Veil Forest**, unable to recall their name, identity or arrival.
2. The forest has luminous, unnatural qi and a corrupted, dangerous atmosphere.
3. The protagonist inspects a shattered jade plaque and a moss-covered spirit stone, then meets **Lin Mei**, a wary herbalist disciple.
4. A **Corrupted Spirit Wolf** threatens them. The player's first experience of **Qi Condensation** follows the confrontation, with Lin Mei astonished at the apparently spontaneous cultivation.
5. They reach **Riverstone Village**; its residents are uneasy, but Lin Mei and **Elder Shen** assist.
6. The player helps with spirit herbs and a village ward, faces the village's threats and receives cultivation guidance.
7. A dream shows cracked heavens, golden chains and an unidentified voice: **“The Dao is dying.”**
8. A morning **Messenger of the Azure Gate** seeks a survivor from the forest, marking the transition into the main story.

These beats are reflected in [config/story.php](../../config/story.php), not an assertion that every broader system is complete or balanced. The design target for the *intro/tutorial* is **2–5 minutes**, not a verified measured duration. The main story begins **after** the sect messenger.

## Storytelling principles

- **Small first, cosmic later.** Begin with one person, one guide, one village. Earn the vast scale.
- **Wonder has a cost.** Qi can heal and empower, yet the forest demonstrates its corruption.
- **The player chooses a path.** Amnesia is a mystery, not an excuse to predetermine the character's morality, name or personality.
- **NPCs have agency.** Lin Mei should remain a person with goals, fear, competence and limits—not only a tutorial marker.
- **Power has consequence.** Breakthroughs, tribulation, sect influence and artifacts should change relationships and obligations.
- **No premature reveals.** Do not explain the origin of the protagonist or the Outer Gods in the prologue.
- **No automatic gameplay rewrites.** This is a narrative reference, not a mandate to adjust cultivation math, combat or progression.

## Reference index

| Document | Focus |
| --- | --- |
| [WORLD_HISTORY.md](WORLD_HISTORY.md) | Recorded myths, historical claims and chronological unknowns |
| [CULTIVATION_COSMOLOGY.md](CULTIVATION_COSMOLOGY.md) | Qi, Dao, cultivation and metaphysical boundaries |
| [FACTIONS_AND_SECTS.md](FACTIONS_AND_SECTS.md) | Sect structure, political stakes and Azure Gate questions |
| [CHARACTERS.md](CHARACTERS.md) | Character motivations, knowledge and dialogue guardrails |
| [REGIONS.md](REGIONS.md) | Starting geography and environmental storytelling |
| [MAIN_STORY.md](MAIN_STORY.md) | The complete existing prologue outline and main-story handoff |
| [MYSTERIES_AND_SECRETS.md](MYSTERIES_AND_SECRETS.md) | Developer-only mystery register and reveal guardrails |

## Existing implementation references

- [Opening scene and dialogue](../../config/story.php)
- [Story progression service](../../app/Services/Story/StoryIntroService.php)
- [Public Codex categories](../../config/codex.php)
- [Existing history-Codex texts](../../app/Services/Codex/CodexCatalogSyncService.php)

**Important:** The in-game Codex already contains history prose. Treat it as **in-universe source material** unless an author explicitly establishes the underlying metaphysics as objective truth.

## Author decisions to settle next

1. What is the structure of the first major story arc beyond Riverstone Village?
2. Is the Azure Gate a named sect, an entry gate to a different sect, or simply a messenger's insignia?
3. What is the exact canonical cultivation realm naming/order? Resolve “Qi Condensation” versus legacy “Qi Refining” before global edits.
4. What is known by ordinary people about the Heavenly Dao, and what remains myth?
5. Which long-term mystery answers should be reserved until later arcs?

## Change protocol

For each explicitly approved world fact, update the appropriate page, mark it **Established**, and record the consequences for other pages. Mark future events **Planned future**, not present-day facts. Proposed content should remain labelled until approved. When implementation follows, compare its text and gameplay dependencies with the Bible; record deliberate divergences rather than silently overwriting them.

## Canon Checkpoint 01 — approved 2026-11-19

Repository documentation update authorized 2026-11-26. Developer-facing only.

| Group | Status | Primary reference |
| --- | --- | --- |
| Sanctuary and Silent Heaven | Canon foundation | CULTIVATION_COSMOLOGY.md; WORLD_HISTORY.md |
| Ancient War and First Fracture | Canon foundation | WORLD_HISTORY.md |
| Scarred Realm and Nine Terraces | Canon foundation | REGIONS.md; WORLD_HISTORY.md |
| Heavenly Titles | Canon foundation | CULTIVATION_COSMOLOGY.md |
| Character foundations | Canon foundation | CHARACTERS.md |
| Future storylines | Planned future, not current-world canon | MAIN_STORY.md; FACTIONS_AND_SECTS.md |

The prologue remains unchanged. Developer knowledge, NPC beliefs, and the player's discovery timeline must not be conflated. The First Fracture is not confirmation of the dream's cracked heavens or the present Dao crisis. The player's origin and the dream speaker remain unresolved; the speaker cannot be the Dao speaking directly. Lin Mei is not yet a Divine Healer, and the Open Lantern has not yet formed at player start.

The **Seal of Ink and Root** is the selected name. Boundless Expanse, Eternal Concord, Eternal Vanguard and Eternal Foundation are provisional terminology for canon concepts; other working labels remain subject to author refinement. Do not infer approval of other Heavenly Titles, exact dates, realm levels, maps, mechanics or every incidental brainstorm. No gameplay changes are authorized by this checkpoint.
