const app = require(`${__dirname}/../package.json`).name;
const { GettextExtractor, JsExtractors } = require('gettext-extractor');
const { decorateJSParserWithVueSupport } = require('gettext-extractor-vue');
const child_process = require("child_process");
const fs = require("fs");
const path = require("path");

(async function() {
    process.chdir(path.join(__dirname, ".."));
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

// For vue@2 support please provide vue-template-compiler via `vue2TemplateCompiler`
    const vueParser = decorateJSParserWithVueSupport(jsParser, {
        vue2TemplateCompiler: require('vue-template-compiler'),
    });
// For vue@3 support please provide @vue/compiler-sfc via `vue3TemplateCompiler`
//const vueParser = decorateJSParserWithVueSupport(jsParser, {
//    vue3TemplateCompiler: require('@vue/compiler-sfc'),
//});


    await vueParser.parseFilesGlob("./src/**/*.@(js|vue)");

    const output = `${__dirname}/templates/${app}.pot`;
    console.info(`Writing to ${output}`)
    extractor.savePotFile(output);
    extractor.printStats();

    function findPHPFiles(dir) {
        let files = [];
        for (let ent of fs.readdirSync(dir, {withFileTypes: true})) {
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