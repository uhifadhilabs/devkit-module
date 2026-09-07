/*
 * The stand-in host's entrypoint. A real one, written by the asset-mapper and
 * stimulus-bundle recipes, imports ./bootstrap.js and starts Stimulus; this one
 * is empty on purpose — what the suite asserts is that the shell's document
 * renders the application's importmap, not what the application put in it.
 */
