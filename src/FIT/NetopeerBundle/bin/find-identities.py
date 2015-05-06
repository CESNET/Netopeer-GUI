#!/usr/bin/python
#
# Copyright (C) 2015 CESNET
#
# LICENSE TERMS
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
# 3. Neither the name of the Company nor the names of its contributors
#    may be used to endorse or promote products derived from this
#    software without specific prior written permission.
#
# ALTERNATIVELY, provided that this notice is retained in full, this
# product may be distributed under the terms of the GNU General Public
# License (GPL) version 2 or later, in which case the provisions
# of the GPL apply INSTEAD OF those given above.
#
# This software is provided ``as is'', and any express or implied
# warranties, including, but not limited to, the implied warranties of
# merchantability and fitness for a particular purpose are disclaimed.
# In no event shall the company or contributors be liable for any
# direct, indirect, incidental, special, exemplary, or consequential
# damages (including, but not limited to, procurement of substitute
# goods or services; loss of use, data, or profits; or business
# interruption) however caused and on any theory of liability, whether
# in contract, strict liability, or tort (including negligence or
# otherwise) arising in any way out of the use of this software, even
# if advised of the possibility of such damage.
#


import os
import xml.etree.ElementTree as ET
import pdb
import json

class IdentityRefTree:
    """Tree of identity-ref used for sharing across data models."""
    name = ""

    def __init__(self, name, prefix, namespace, base):
        self.name = name
        self.prefix = prefix
        self.namespace = namespace
        self.baseIdentity = base
        self.children = []

    def __eq__(self, name):
        if self.name == name:
            return True
        else:
            return False

    def addChild(self, child):
        if child.__class__ is list:
            for ch in child:
                if ch not in self.children:
                    self.children.append(ch)
        else:
            if child not in self.children:
                self.children.append(child)

    def getName(self):
        if self.prefix is not "":
            return self.prefix + ":" + self.name
        else:
            return self.name

    def isChildOf(self, name):
        if self.baseIdentity == name:
            return True
        else:
            return False

    def __str__(self):
        s = self.prefix + ":" + self.name
        if self.baseIdentity:
            s += " < " + self.baseIdentity
        s += " ("
        self.children.sort()
        s += self.children.__len__().__str__()
        for i in self.children:
            s += " " + i.getName()
        s += " )"
        return s

reflist = []
models = {}

def findExistingIdentity(name):
    for i in reflist:
        if i == name:
            return i
    return None

files = [f for f in os.listdir(os.curdir) if os.path.isfile(f) and f.endswith(".yin")]
for f in files:
    with open(f, "r") as fd:
        root = ET.fromstringlist(fd.readlines())

        currentPrefix = ""
        currentNS = ""
        moduleName = root.attrib["name"]
        imports = {}

        for prefix in root.findall('{urn:ietf:params:xml:ns:yang:yin:1}namespace'):
            currentNS = prefix.attrib["uri"]
        for prefix in root.findall('{urn:ietf:params:xml:ns:yang:yin:1}prefix'):
            currentPrefix = prefix.attrib["value"]

        # TODO additional check of prefixes and namespaces
        #for i in root.findall('{urn:ietf:params:xml:ns:yang:yin:1}import'):
        #    print(vars(prefix))

        models[moduleName] = {"ns": currentNS, "prefix": currentPrefix}

        for child in root.findall('{urn:ietf:params:xml:ns:yang:yin:1}identity'):
            base = child.find('{urn:ietf:params:xml:ns:yang:yin:1}base')
            if base is not None:
                if ":" in base.attrib["name"]:
                    t = dict(zip(["prefix", "name"], base.attrib["name"].split(":")))
                    basename = t["name"]
                else:
                    basename = base.attrib["name"]
            else:
                basename = ""
            status = child.find('{urn:ietf:params:xml:ns:yang:yin:1}status')
            if status is not None:
                if status.attrib["value"] in ["deprecated", "obsolete"]:
                    continue
            newIdentity = IdentityRefTree(child.attrib["name"], currentPrefix, currentNS, basename)
            ex = findExistingIdentity(basename)
            if ex is not None:
                ex.addChild(newIdentity)
            else:
                reflist.append(newIdentity)
            for old in reflist:
                #extends some identity?
                if old.isChildOf(newIdentity.name):
                    newIdentity.addChild(old.children)
    fd.close()

identities = {}
for i in reflist:
    identities[i.getName()] = [ch.getName() for ch in i.children]

prefixes = {}
for i in models:
    prefixes[models[i]["prefix"]] = models[i]["ns"]

data = {"prefixes": prefixes, "identities": identities}

print(json.dumps(data))

