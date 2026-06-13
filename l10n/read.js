import fs from "fs";
import path from "path";
import child_process from "child_process";
import {decorateJSParserWithVueSupport} from "gettext-extractor-vue";
import {GettextExtractor, JsExtractors} from "gettext-extractor";

(async function() {
    process.chdir(path.join(import.meta.dirname, ".."));
    const app = JSON.parse(fs.readFileSync("package.json", "utf8")).name;
    const extractor = new GettextExtractor();

    const jsParser = extractor.createJsParser([
        JsExtractors.callExpression('t', {
            arguments: {
                text: 1,
                context: 2,
            },
        }),
        JsExtractors.callExpression('n', {
            arguments: {
                text: 1,
                textPlural: 2,
                context: 3,
            },
        }),
    ]);

    const vueParser = decorateJSParserWithVueSupport(jsParser, {
        vue3TemplateCompiler: require('@vue/compiler-sfc'),
    });

    await vueParser.parseFilesGlob("./src/**/*.@(js|vue)");

    const output = `${__dirname}/templates/${app}.pot`;
    console.info(`Writing to ${output}`)
    extractor.savePotFile(output);
    extractor.printStats();

    function findPHPFiles(dir) {
        let files = [];
        for (let ent of fs.readdirSync(dir, {withFileTypes: true})) {
            if (dir === "." && ent.name === "vendor") {
                continue
            }

            if (ent.isDirectory()) {
                files.push(...findPHPFiles(path.join(dir, ent.name)));
            } else if (ent.isFile() && ent.name.endsWith(".php")) {
                files.push(path.join(dir, ent.name));
            }
        }
        return files;
    }

    for (let file of findPHPFiles(".")) {
        console.info(`  Reading PHP: ${file}`);
        child_process.execSync(`xgettext --output="${output}" --join-existing --keyword=t --keyword=n:1,2 --language=PHP "${file}" --add-comments=TRANSLATORS --from-code=UTF-8 --package-version="8.0.0" --package-name="Nextcloud app ${app}" --msgid-bugs-address="marco+nc@ziech.net"`);
    }

    console.info("Done.");
})();