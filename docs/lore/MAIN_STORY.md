# Main Story Register — Prologue to Chapter One

**Status:** Canon Checkpoint 01: existing prologue preserved; later arcs are planned future, not implemented or current-world events.

## Narrative structure

- **Opening intro:** Brief, atmospheric text and memory fragments.
- **Tutorial prologue:** Target **2–5 minutes** for a first-time player; a design goal, not a measured completion time.
- **Main story:** Begins **after** the sect messenger at the end of the tutorial. Its immediate plot remains open for development; longer-term planned arcs appear below.

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

## Checkpoint 01 — planned future narrative register

All events below are future plans, not current-world facts or implemented quests. Exact timing, chapter counts, dialogue, outcomes, level gates and ordering between campaigns remain flexible. The existing 13-step tutorial is unchanged. Start foreshadowing after the messenger; preserve the player's agency, unknown origin and unidentified dream voice. NPCs have independent lives; no single MMORPG player is assumed to own their destiny.

### Scarred Realm campaign

- Early post-tutorial: Lin Mei studies Hua Qinglan's herbal writings; practical formation methods associated with Zhao Wenli and a recurring lantern/unfinished-road motif may foreshadow Lantern Hollow. No new claims about Riverstone's ward or the forest's cosmic cause.
- Discovery of the Ninth Terrace: scattered records and present wilderness observations lead to the observatory; investigators Wei Lian, Mu Qing and Xun Kaifeng compare evidence and confront the Unfinished Alignment. The Scholar's Seal measures observable results, not worthiness; the Covenant Seal protects environmental stability. A controlled First Safe Opening gives access to selected records without granting automatic deep-vault access.
- Lost Testimonies: Yan Shou, Qiao Ning and Han Zhiren's evacuation records reveal human choices and unconfirmed departures. Preserve uncertainty about individual custodians. Han's leadership of Lantern Hollow's founding refugees is historical canon, not a future event.
- Second Pattern: the Lantern Causeway's transformed formations provide a second local adaptation example. A safely traversable section and shelter inscriptions may lead to further investigation. Players discover the present-day community and connections to Hua, Zhao and Mo; the exact order of settlement and archive discoveries is flexible. Hua recognizes her inherited medical tradition, Zhao confronts changed engineering, Mo advocates safeguards. None gains omniscient cosmic knowledge.
- Fracture's Heart remains a later investigation; no automatic Anchor repair, reunion of the Divided Self or revelation of the dream speaker.

### Lin Mei and the medical campaign — planned sequence

| Arc | Planned beats |
| --- | --- |
| The Withering Valley / Garden That Would Not Heal | Post-tutorial reading of Hua's observations leads to a regional investigation. Distinguish genuinely damaged plants from possibly healthy changes; Lin Mei and Hua test competing ideas. Informal mentorship develops, formal discipleship open. |
| The Second Path / Turning Pulse | Qin Yao's surviving observations reach Yun; Luo Xinyi, a navigator, experiences dangerous transitions between two functioning meridian configurations after a spiritual storm. Yun, Hua, Lin Mei and Xinyi collaborate. Supported transitions help Xinyi regain some independence, not a universal cure. Transitional Healing develops; Yun's imprint shows faint unexplained behavior without active Grace. |
| Great Medical Dispute | At the proposed Confluence of Hundred Remedies, Pei Luyan challenges extrapolation from one case. An inappropriate application harms a patient requiring conventional restoration. Independent testing and collaboration yield the Three Questions of the Turning Pulse: what changes, what remains stable, what intervention does. Recognition grows, no Heavenly Title yet. |
| Open Lantern / Winter Road | Lin Mei chooses traveling research and care; a cooperative network develops. Isolated winter settlements test supplies, transport, local expertise and cooperation. Duan Rui and other physicians contribute. |
| Price of a Miracle | Su Yiren proposes large-scale funding/distribution. An incomplete Harmonizing Veins Pill is marketed as universal treatment; Jian Yu suffers harm and receives care. Investigate distribution failures; Su cooperates after mistakes. Open Lantern Compact establishes shared standards and limits on unsafe use. Zhao designs maintainable clinics; Jian Yu may become a medicine-quality apprentice. |
| Great Affliction / Pale Thread | Unusual spiritual regulation illness appears across regions. Tao Fen identifies a clue linking cases to transported river reeds. Working natural cause: Riverlace, a wetland spiritual microorganism whose persistence in transported material is enabled by changed conditions. No assumed link to the Dao crisis or Ancient War. Pei falls ill and survives with gradual recovery; Mo assists faction cooperation; Su, Duan, Zhao, Hua and Yun contribute. Exposure control, patient-specific care and shared evidence matter; not all patients recover. Exact disease details remain planned. |
| A Small Light Beneath Heaven | After accumulated achievements and widespread real influence, Lin Mei eventually receives the rare Divine Healer title through a nonverbal Law Imprint manifestation and Grace of Living Harmony. No fixed date or automatic reward after the Affliction. She continues traveling and caring for ordinary people. |
| Silence Between Graces / Renewal of the Silent Pulse | Yun studies historical title holders and Qin Yao's observations; later compares her dormant imprint with Lin Mei's active one. Second Harmony is a working hypothesis. Failed attempts give way to studying stable responses; planned renewal as Grace of the Quiet Interval supports temporary stability in certain transitions, not unlimited preservation. Treatise of the Second Harmony records one case, not a guaranteed renewal method. |
| Legacy of the Open Lantern / Lantern Schism | Network matures into a federation; disagreements over funding, autonomy and experimentation lead to accountable governance. Lin Mei refuses authority based solely on her title. Local practitioners improve methods without her presence. |

The medical storyline is one branch of the wider MMORPG, not a replacement for sect politics, cultivation progression or other adventures. Players may contribute via exploration, combat protection, logistics, gathering and research. No new mechanics, rewards, fixed gameplay thresholds or content implementation authorized here.
