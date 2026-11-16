# Main Story Register — Prologue to Chapter One

**Status:** Prologue outline based on actual configured scenes. Chapter One after the messenger is **not written/implemented as a full arc** in this Bible.

## Narrative structure

- **Opening intro:** Brief, atmospheric text and memory fragments.
- **Tutorial prologue:** Target **2–5 minutes** for a first-time player; a design goal, not a measured completion time.
- **Main story:** Begins **after** the sect messenger at the end of the tutorial. Its plot remains open for development.

The existing story uses a 13-step flow in [config/story.php](../../config/story.php). This file documents that flow; it does not claim that prose length, browser timing or combat difficulty has passed a 2–5 minute usability test.

## Prologue — “The Lost Soul” (working narrative title)

**Location:** Spirit Veil Forest → Riverstone Village  
**Emotional arc:** Disorientation → suspicion → danger → wonder → fragile belonging → unease → invitation to the wider world.

| Step key | Scene | Narrative purpose | Player-facing interaction |
| --- | --- | --- | --- |
| awakening | Awaken beneath an uncanny sky | Loss of identity and sensory wonder | Continue |
| explore_forest | Travel in the mist | Name and danger of the forest | Follow light |
| inspect_objects | Jade plaque and spirit stone | First evidence of past visitors and current danger | Inspect both |
| meet_lin_mei | Lin Mei emerges with herb basket | First ally, world perspective, warning | Dialogue/continue |
| fight_wolf | Corrupted Spirit Wolf | Danger and first combat tutorial | Fight; can retry |
| qi_condensation | Qi gathers within protagonist | Exceptional first cultivation | Continue |
| breakthrough_cinematic | Meridians open | First progress beat and visual payoff | Cinematic/continue |
| village_arrival | Enter Riverstone | Fear, shelter and community | Continue |
| quest_herbs | Help Lin Mei prepare herbs | Useful action beyond battle | Complete task |
| quest_defend | Defend the village ward | Protect others; link corruption to local stakes | Complete task |
| quest_elder | Elder Shen speaks | Introduce cultivation discipline and responsibility | Dialogue/continue |
| dream_heavens | Dream of cracked heavens | Cosmic question, no answer | Wake |
| sect_messenger | An emissary arrives at dawn | Larger world notices protagonist | Finish intro |

### Suggested cold-open copy — Proposed

> The heavens remember the passage of every soul.  
> Somewhere beyond memory, a name has been erased.  
> You open your eyes beneath a sky you do not recognize.

This is *optional additional copy*, not in the current scene configuration.

### Mandatory continuity notes

- **The prologue already uses a jade plaque and a spirit stone**, not a broken sword. The broken sword was an earlier brainstorm and should not be reinserted as canon.
- The village has **Elder Shen**, and the emissary is called **Messenger of the Azure Gate**. Use these configured names until changed through an explicit author decision.
- The dream's exact line is **“The Dao is dying.”** Do not follow it with an explanation of the speaker.
- The first cultivation experience is **Qi Condensation**, as currently worded in the tutorial; the wider realm nomenclature needs an explicit naming decision.
- Player identity should remain player-defined. The current script contains some default masculine references; future writing should favor character-neutral language unless player settings establish otherwise.
- Lin Mei should not inexplicably know all details of the protagonist's history.

### Pacing risks for the 2–5 minute goal

Thirteen steps, two object inspections, combat, three village interactions and several dialogue screens can exceed five minutes for a new player. Do not delete story beats arbitrarily; instead measure actual completion time, reduce excess clicks, and make all mandatory teaching context immediately useful. A later UX pass can split optional detail into Codex discoveries.

## Chapter One — Beyond the Veil (working title; **Proposed**)

**Premise:** The messenger's arrival turns a private survival story into a social and political problem. The player must decide whom to trust, while their unusual cultivation begins attracting interpretations and expectations.

Possible nonexclusive story threads for review:
1. **The invitation:** Journey toward an outer sect, learn its rules, meet peers with conflicting motivations.
2. **A debt to Riverstone:** The village's ward or supply network is threatened again, giving the protagonist a reason to return and helping Lin Mei remain relevant.
3. **A contradiction in the records:** A sect archive or elderly witness has an account of the Veil that conflicts with what Lin Mei believes.

These are narrative proposals, not assigned quests or approved fixed-choice plot outcomes.

## A sensible early main-story structure — Proposed

- **Act I — The Outsider:** Find belonging; learn foundations; choose how to respond to sect interest.
- **Act II — Unquiet Qi:** Local disturbances show a pattern; investigate without identifying a cosmic culprit.
- **Act III — The Price of Knowledge:** Competing factions offer mutually incomplete explanations; decisions have consequences.
- **Act IV — A Wider Horizon:** The first substantial discovery changes the stakes, while preserving the deepest origin mysteries.

Do not invent chapter counts, level gates, quest rewards or sect assignments until gameplay integration is scoped.

## Acceptance criteria for future story implementation

- Prologue continuity matches configuration or records approved changes.
- All mandatory steps are reachable, replay-safe and cannot duplicate rewards.
- Failure/retry paths remain narratively coherent.
- Skip/progress and tutorial completion behavior are preserved when code work eventually occurs.
- Unlocked public Codex entries don't reveal developer-only mysteries.
- Opening flow is tested for its intended 2–5 minute duration, with an explicit report of measured results.
