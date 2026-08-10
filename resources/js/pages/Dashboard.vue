<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ChevronDown, Download, Shield, Trophy, Upload, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { dashboard } from '@/routes';

const TOP_RIGHT_DIALOG_CLASS =
    'top-4 right-4 left-auto bottom-auto translate-x-0 translate-y-0 sm:max-w-md';

interface Player {
    id: number;
    name: string;
    position: string;
    shirt_number: number;
    goals: number;
}

interface ClubItem {
    id: number;
    name: string;
    championship: string;
    players_count: number;
    players: Player[];
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface ChampionshipCount {
    championship: string;
    total: number;
}

const props = defineProps<{
    stats: {
        totalClubs: number;
        totalPlayers: number;
    };
    clubsByChampionship: ChampionshipCount[];
    clubs: Paginated<ClubItem>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const numberFormatter = new Intl.NumberFormat('pt-BR');

const TOP_CHAMPIONSHIPS = 5;

const championshipRows = computed(() => {
    const rows = props.clubsByChampionship.map((row) => ({
        label: row.championship || 'Sem série',
        total: row.total,
    }));

    if (rows.length <= TOP_CHAMPIONSHIPS) {
        return rows;
    }

    const top = rows.slice(0, TOP_CHAMPIONSHIPS);
    const otherTotal = rows.slice(TOP_CHAMPIONSHIPS).reduce((sum, row) => sum + row.total, 0);

    return [...top, { label: 'Outras', total: otherTotal }];
});

const maxChampionshipTotal = computed(() => Math.max(...championshipRows.value.map((row) => row.total), 1));

function barWidth(total: number): string {
    return `${Math.max((total / maxChampionshipTotal.value) * 100, 2)}%`;
}

const openClubs = ref(new Set<number>());

function toggleClub(id: number) {
    if (openClubs.value.has(id)) {
        openClubs.value.delete(id);
    } else {
        openClubs.value.add(id);
    }
}

const importOpen = ref(false);
const exportOpen = ref(false);
const exportChampionship = ref('all');
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Shield class="size-4" />
                        Total de clubes
                    </CardDescription>
                    <CardTitle class="text-3xl font-semibold tabular-nums">
                        {{ numberFormatter.format(stats.totalClubs) }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Users class="size-4" />
                        Total de jogadores
                    </CardDescription>
                    <CardTitle class="text-3xl font-semibold tabular-nums">
                        {{ numberFormatter.format(stats.totalPlayers) }}
                    </CardTitle>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <CardDescription class="flex items-center gap-2">
                        <Trophy class="size-4" />
                        Clubes por série
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="championshipRows.length" class="space-y-2.5">
                        <div v-for="row in championshipRows" :key="row.label" class="flex items-center gap-2">
                            <span class="w-16 shrink-0 truncate text-xs text-muted-foreground" :title="row.label">
                                {{ row.label }}
                            </span>
                            <div class="h-4 flex-1 border-l border-border">
                                <div
                                    class="h-4 rounded-r bg-chart-1"
                                    :style="{ width: barWidth(row.total) }"
                                />
                            </div>
                            <span class="w-8 shrink-0 text-right text-xs font-medium tabular-nums">
                                {{ row.total }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">Nenhum clube cadastrado ainda.</p>
                </CardContent>
            </Card>
        </div>

        <Card class="flex-1">
            <CardHeader>
                <CardTitle>Clubes e jogadores</CardTitle>
                <CardDescription>Lista de clubes, a série em que competem e seu elenco.</CardDescription>
                <CardAction class="flex gap-2">
                    <Dialog v-model:open="importOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm">
                                <Upload class="size-4" />
                                Importar
                            </Button>
                        </DialogTrigger>
                        <DialogContent :class="TOP_RIGHT_DIALOG_CLASS">
                            <DialogHeader>
                                <DialogTitle>Importar dados</DialogTitle>
                                <DialogDescription>
                                    Envie um arquivo com clubes e jogadores para processamento em segundo plano.
                                </DialogDescription>
                            </DialogHeader>

                            <Form
                                v-bind="ImportController.index.form()"
                                reset-on-success
                                @success="importOpen = false"
                                v-slot="{ errors, processing, progress }"
                                class="space-y-2"
                            >
                                <input
                                    type="file"
                                    name="file"
                                    accept=".jsonl,.ndjson,.json"
                                    required
                                    class="file:text-foreground placeholder:text-muted-foreground border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none file:mr-2 file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                />
                                <InputError :message="errors.file" />
                                <p class="text-xs text-muted-foreground">Formatos aceitos: .jsonl, .ndjson, .json (máx. 2MB).</p>
                                <div v-if="progress" class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div class="h-full bg-chart-1" :style="{ width: progress.percentage + '%' }" />
                                </div>
                                <Button type="submit" :disabled="processing" size="sm">
                                    {{ processing ? 'Enviando...' : 'Importar' }}
                                </Button>
                            </Form>
                        </DialogContent>
                    </Dialog>

                    <Dialog v-model:open="exportOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm">
                                <Download class="size-4" />
                                Exportar
                            </Button>
                        </DialogTrigger>
                        <DialogContent :class="TOP_RIGHT_DIALOG_CLASS">
                            <DialogHeader>
                                <DialogTitle>Exportar dados (CSV)</DialogTitle>
                                <DialogDescription>Escolha a série e baixe os clubes ou jogadores em CSV.</DialogDescription>
                            </DialogHeader>

                            <div class="space-y-2">
                                <Label for="export-championship">Série</Label>
                                <Select v-model="exportChampionship">
                                    <SelectTrigger id="export-championship" class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Todas (Série A e B)</SelectItem>
                                        <SelectItem value="SERIE A">Série A</SelectItem>
                                        <SelectItem value="SERIE B">Série B</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex gap-2">
                                <Button as-child variant="outline" size="sm">
                                    <a :href="ExportController.index.url({ query: { file: 'clubs', championship: exportChampionship } })">
                                        Clubes
                                    </a>
                                </Button>
                                <Button as-child variant="outline" size="sm">
                                    <a :href="ExportController.index.url({ query: { file: 'players', championship: exportChampionship } })">
                                        Jogadores
                                    </a>
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </CardAction>
            </CardHeader>
            <CardContent>
                <div v-if="clubs.data.length" class="divide-y divide-border">
                    <Collapsible
                        v-for="club in clubs.data"
                        :key="club.id"
                        :open="openClubs.has(club.id)"
                        @update:open="toggleClub(club.id)"
                    >
                        <CollapsibleTrigger class="flex w-full items-center gap-3 py-3 text-left">
                            <ChevronDown
                                class="size-4 shrink-0 text-muted-foreground transition-transform"
                                :class="{ '-rotate-90': !openClubs.has(club.id) }"
                            />
                            <span class="flex-1 truncate font-medium">{{ club.name }}</span>
                            <Badge variant="outline">{{ club.championship || 'Sem série' }}</Badge>
                            <span class="w-24 shrink-0 text-right text-sm text-muted-foreground">
                                {{ numberFormatter.format(club.players_count) }} jogadores
                            </span>
                        </CollapsibleTrigger>
                        <CollapsibleContent class="pb-4 pl-7">
                            <div v-if="club.players.length" class="overflow-x-auto rounded-md border border-border">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-border text-left text-xs text-muted-foreground">
                                            <th class="w-12 px-3 py-2 font-medium">#</th>
                                            <th class="px-3 py-2 font-medium">Nome</th>
                                            <th class="px-3 py-2 font-medium">Posição</th>
                                            <th class="px-3 py-2 text-right font-medium">Gols</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="player in club.players"
                                            :key="player.id"
                                            class="border-b border-border last:border-0"
                                        >
                                            <td class="px-3 py-2 tabular-nums text-muted-foreground">
                                                {{ player.shirt_number }}
                                            </td>
                                            <td class="px-3 py-2">{{ player.name }}</td>
                                            <td class="px-3 py-2 text-muted-foreground">{{ player.position }}</td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ player.goals }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Nenhum jogador cadastrado para este clube.</p>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nenhum clube cadastrado ainda.</p>

                <div v-if="clubs.last_page > 1" class="mt-4 flex items-center justify-between border-t border-border pt-4">
                    <p class="text-sm text-muted-foreground">
                        Página {{ clubs.current_page }} de {{ clubs.last_page }} &middot; {{ numberFormatter.format(clubs.total) }} clubes
                    </p>
                    <div class="flex gap-2">
                        <Button v-if="clubs.prev_page_url" as-child variant="outline" size="sm">
                            <Link :href="clubs.prev_page_url" :only="['clubs']" preserve-scroll>Anterior</Link>
                        </Button>
                        <Button v-else variant="outline" size="sm" disabled>Anterior</Button>

                        <Button v-if="clubs.next_page_url" as-child variant="outline" size="sm">
                            <Link :href="clubs.next_page_url" :only="['clubs']" preserve-scroll>Próxima</Link>
                        </Button>
                        <Button v-else variant="outline" size="sm" disabled>Próxima</Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
