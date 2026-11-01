// Writes the patch notes for a release from the commits that changed the app (mobile_app/) since the version before:
// release-notes.md for the release page, which the server reads for the "Update available" pop-up, and
// patch-notes.b64 with this and earlier versions for the app's Patch notes pop-up (--dart-define=PATCH_NOTES).
//
// A note is the commit's subject. To reword it or leave it out, add a line to the commit message:
//   Patch-note: You can now track returns step by step.
//   Patch-note: none
//
// Usage: node patch-notes.mjs v1.0.25 output-folder
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const VERSIONS_IN_APP = 5;
const NOTES_PER_VERSION = 8;
const NOTHING_LISTED = 'Behind-the-scenes improvements and fixes.';

// Commit types that never change what people see, and the "feat(mobile):" style prefix on the ones that can.
const INTERNAL = /^(test|tests|ci|chore|build|docs)(\(.*?\))?!?:/i;
const TYPE_PREFIX = /^[a-z]+(\(.*?\))?!?:\s*/i;

const git = (...args) => execFileSync('git', args, { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });

const parts = (tag) => tag.replace(/^v/, '').split('.').map(Number);

const newestFirst = (a, b) => {
    const [x, y] = [parts(a), parts(b)];
    return y[0] - x[0] || y[1] - x[1] || y[2] - x[2];
};

const noteFrom = (subject, body) => {
    const override = body.match(/^Patch-note:[ \t]*(.+)$/im)?.[1].trim();
    if (override) return override.toLowerCase() === 'none' ? null : override;
    if (INTERNAL.test(subject)) return null;
    const text = subject.replace(TYPE_PREFIX, '').trim();
    return text.charAt(0).toUpperCase() + text.slice(1);
};

const notesBetween = (from, to) => {
    const range = from ? `${from}..${to}` : to;
    const commits = git('log', '--no-merges', '--format=%s%x00%b%x1e', range, '--', ':/mobile_app').split('\x1e');
    const notes = commits
        .filter((commit) => commit.trim())
        .map((commit) => {
            const [subject, body = ''] = commit.trim().split('\x00');
            return noteFrom(subject, body);
        })
        .filter(Boolean);
    const unique = [...new Set(notes)].slice(0, NOTES_PER_VERSION);
    return unique.length ? unique : [NOTHING_LISTED];
};

const [tag, output] = process.argv.slice(2);
if (!/^v\d+\.\d+\.\d+$/.test(tag ?? '') || !output) {
    console.error('Usage: node patch-notes.mjs v1.0.25 output-folder');
    process.exit(1);
}

// The tag may not exist yet: the release creates it on this commit.
const earlier = git('tag', '--list', 'v[0-9]*.[0-9]*.[0-9]*')
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => /^v\d+\.\d+\.\d+$/.test(line) && newestFirst(line, tag) > 0)
    .sort(newestFirst);

const refs = ['HEAD', ...earlier];
const versions = [tag, ...earlier].slice(0, VERSIONS_IN_APP).map((name, index) => ({
    version: name.replace(/^v/, ''),
    highlights: notesBetween(earlier[index], refs[index]),
}));

mkdirSync(output, { recursive: true });
writeFileSync(join(output, 'release-notes.md'), `${versions[0].highlights.map((note) => `- ${note}`).join('\n')}\n`);
writeFileSync(join(output, 'patch-notes.b64'), Buffer.from(JSON.stringify(versions)).toString('base64'));
console.log(JSON.stringify(versions, null, 2));
