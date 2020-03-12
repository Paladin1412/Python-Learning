import subprocess


class MonkeyTester:
    def __init__(self, times):
        self.times = times

    @classmethod
    def call_sys(cls, command):
        ret = subprocess.Popen(command, shell=True, stdout=subprocess.PIPE)
        return ret.stdout.read().decode('utf-8')

    def monkey_test(self):
        if "Monkey finished" not in self.call_sys(
                "adb shell monkey -p com.xiaodianshi.tv.yst --throttle 40 -vvv 500"):
            raise Exception("monkey test failed")

    def check_ui(self) -> bool:
        self.call_sys("adb shell uiautomator dump /sdcard/ui.xml")
        if self.call_sys('adb shell cat /sdcard/ui.xml | grep "我的"'):
            return False
        return True

    def back_to_index(self):
        print("Check if monkey is in index!")
        while self.check_ui():
            print("Monkey is out of index,now back to last page.")
            subprocess.call("adb shell input keyevent 4", shell=True)
        print("continue testing")

    def main(self):
        print("Begin to test...")
        for i in range(self.times):
            self.monkey_test()
            self.back_to_index()


if __name__ == '__main__':
    mk = MonkeyTester(10)# 时间，单位为每20秒
    mk.main()
