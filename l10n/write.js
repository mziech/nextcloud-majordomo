const app = require(`${__dirname}/../package.json`).name;
const fs = require('fs');
const gettextParser = require("gettext-parser");

process.chdir(__dirname);

for (let ent of fs.readdirSync(__dirname, { withFileTypes: true })) {
    const filename = `${__dirname}/${ent.name}/${app}.po`;
    if (ent.isDirectory() && fs.existsSync(filename)) {
        console.info(`Writing: ${ent.name}`)
        const input = fs.readFileSync(filename);
        const po = gettextParser.po.parse(input);
        const out = {translations: {}, pluralForm: po.headers["Plural-Forms"]};
        for (let translation of Object.values(po.translations[""])) {
            if (translation.msgid === "") continue;
            out.translations[translation.msgid] = translation.msgstr.length === 1
                ? translation.msgstr[0] : translation.msgstr.length;
        }
        fs.writeFileSync(`${ent.name}.json`, JSON.stringify(out, null, 2));
        fs.writeFileSync(`${ent.name}.js`, `OC.L10N.register(${JSON.stringify(app)}, ${JSON.stringify(out.translations, null, 2)}, ${JSON.stringify(out.pluralForm)});\n`);
    }
}
